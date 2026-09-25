<?php

declare(strict_types=1);

namespace Credova\Service\PaymentTransactionStateHandler;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;

readonly class CredovaTransactionStateHandler
{
    public function __construct(
        private StateMachineRegistry $stateMachineRegistry,
        private OrderTransactionStateHandler $transactionStateHandler
    ) {
    }

    public function credovaApproved(string $transactionId, Context $context): void
    {
        $this->stateMachineRegistry->transition(
            new Transition(
                'order_transaction',
                $transactionId,
                'credova_approved',
                'stateId'
            ),
            $context
        );
    }

    public function credovaSigned(string $transactionId, Context $context): void
    {
        $this->stateMachineRegistry->transition(
            new Transition(
                'order_transaction',
                $transactionId,
                'credova_signed',
                'stateId'
            ),
            $context
        );
    }
    public function cancelOrderAndTransaction(string $orderId, string $orderTransactionId, Context $context): void
    {
        $this->transactionStateHandler->cancel($orderTransactionId, $context);

        $this->stateMachineRegistry->transition(
            new Transition(
                'order',
                $orderId,
                'cancel',
                'stateId'
            ),
            $context
        );
    }
}
