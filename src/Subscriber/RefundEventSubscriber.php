<?php

namespace Credova\Subscriber;

use Credova\Gateways\CredovaHandler;
use Credova\Service\PaymentClientApi;
use Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class RefundEventSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly PaymentClientApi $paymentClient)
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

        if (($customFields['credovaStatus'] ?? null) !== 'Signed' || empty($customFields['credovaPublicId'])) {
            return;
        }

        $publicId = $customFields['credovaPublicId'];
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

        $this->paymentClient->returnApplication($publicId, $payload);
    }
}
