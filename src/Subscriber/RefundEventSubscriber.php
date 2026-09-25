<?php

declare(strict_types=1);

namespace Credova\Subscriber;

use Credova\Gateways\CredovaHandler;
use Credova\Library\Constants\CredovaFields;
use Credova\Service\PaymentClientApi;
use Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class RefundEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly PaymentClientApi $paymentClient,
        private readonly ?EntityRepository $orderReturnRepository
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'state_enter.order_transaction.state.refunded' => 'onOrderRefund',
            'state_enter.order_transaction.state.refunded_partially' => 'onOrderRefundPartially',
        ];
    }

    private function handleRefund(OrderStateMachineStateChangeEvent $event, array $extraPayload): void
    {
        $customFields = $event->getOrder()->getCustomFields();

        if (($customFields[CredovaFields::ORDER_STATUS] ?? null) !== CredovaFields::STATUS_SIGNED) {
            return;
        }

        $publicId = $customFields[CredovaFields::ORDER_PUBLIC_ID] ?? null;
        if (!$publicId) {
            return;
        }

        $payload = array_filter(array_merge([
            'agentName' => $customFields[CredovaFields::ORDER_AGENT_NAME] ?? 'Edon Agent',
            'phone' => $customFields[CredovaFields::ORDER_PHONE] ?? null,
            'email' => $customFields[CredovaFields::ORDER_EMAIL] ?? null,
        ], $extraPayload), static fn($value) => $value !== null);

        $this->paymentClient->returnApplication($publicId, $payload);
    }

    public function onOrderRefund(OrderStateMachineStateChangeEvent $event): void
    {
        if (!$this->isCredovaPayment($event)) {
            return;
        }

        $this->handleRefund($event, [
            'reason' => 'Fully Refund.',
            'returnType' => '2',
        ]);
    }

    public function onOrderRefundPartially(OrderStateMachineStateChangeEvent $event): void
    {
        if (!$this->isCredovaPayment($event)) {
            return;
        }
        if (!$this->orderReturnRepository) {
            return;
        }

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('orderId', $event->getOrder()->getId()))
            ->addAssociation('lineItems');

        $orderReturn = $this->orderReturnRepository
            ->search($criteria, $event->getContext())
            ->getEntities()
            ->first();

        $this->handleRefund($event, [
            'reason' => $this->extractReturnReason($orderReturn),
            'returnType' => '4',
            'amount' => $this->extractReturnAmount($orderReturn),
        ]);
    }

    private function isCredovaPayment(OrderStateMachineStateChangeEvent $event): bool
    {
        $transactions = $event->getOrder()->getTransactions();
        if ($transactions === null) {
            return false;
        }

        foreach ($transactions as $transaction) {
            if ($transaction->getPaymentMethod()?->getHandlerIdentifier() === CredovaHandler::class) {
                return true;
            }
        }

        return false;
    }

    private function extractReturnReason(?Entity $orderReturn): string
    {
        if ($orderReturn === null || !$orderReturn->has('lineItems')) {
            return 'Client requested a return.';
        }

        $lineItems = $orderReturn->get('lineItems');
        if (!$lineItems instanceof EntityCollection) {
            return 'Client requested a return.';
        }

        $firstLineItem = $lineItems->first();
        if (!$firstLineItem instanceof Entity || !$firstLineItem->has('internalComment')) {
            return 'Client requested a return.';
        }

        $internalComment = $firstLineItem->get('internalComment');

        return is_string($internalComment) && $internalComment !== ''
            ? $internalComment
            : 'Client requested a return.';
    }

    private function extractReturnAmount(?Entity $orderReturn): ?float
    {
        if ($orderReturn === null || !$orderReturn->has('amountTotal')) {
            return null;
        }

        $amountTotal = $orderReturn->get('amountTotal');

        return is_numeric($amountTotal) ? (float) $amountTotal : null;
    }
}
