<?php

namespace Credova\Subscriber;

use Credova\Gateways\CredovaHandler;
use Credova\Service\PaymentClientApi;
use Credova\Exception\CredovaApiException;
use Credova\Library\Constants\CredovaFields;
use Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Psr\Log\LoggerInterface;

class RefundEventSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly PaymentClientApi $paymentClient, private readonly LoggerInterface $logger)
    {
    }

    public static function getSubscribedEvents()
    {
        return [
        'state_enter.order_transaction.state.refunded' => 'onStateMachineTransition',
        ];
    }

    public function onStateMachineTransition(OrderStateMachineStateChangeEvent $event): void
    {
        $order = $event->getOrder();
        $orderTransaction = $order->getTransactions()->first();

        if (!$orderTransaction) {
            return;
        }

        $handlerIdentifier = $orderTransaction->getPaymentMethod()?->getHandlerIdentifier();
        if ($handlerIdentifier !== CredovaHandler::class) {
            return;
        }

        $customFields = $order->getCustomFields() ?? [];

        if (($customFields[CredovaFields::ORDER_CF_STATUS] ?? null) !== CredovaFields::STATUS_SIGNED || empty($customFields[CredovaFields::ORDER_CF_PUBLIC_ID])) {
            return;
        }

        $publicId = $customFields[CredovaFields::ORDER_CF_PUBLIC_ID];
        $payload = [
        'agentName'  => $customFields['credovaAgentName'] ?? 'Edon Agent',
        'phone'      => $customFields['credovaPhone'] ?? null,
        'email'      => $customFields['credovaEmail'] ?? null,
        'reason'     => $customFields['credovaReason'] ?? 'Client requested a return.',
        'returnType' => $customFields['credovaReturnType'] ?? '2',
        ];

        foreach ($payload as $key => $value) {
            if ($value === null) {
                unset($payload[$key]);
            }
        }

        try {
            $this->paymentClient->returnApplication($publicId, $payload);
        } catch (CredovaApiException $e) {
            $this->logger->warning('Credova return application failed', ['reason' => $e->getMessage(), 'publicId' => $publicId]);
        }
    }
}
