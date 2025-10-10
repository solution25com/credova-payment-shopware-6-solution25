<?php

namespace Credova\Service\PaymentTransactionStateHandler;

use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;
use Shopware\Core\Framework\Context;

readonly class CredovaTransactionStateHandler
{
  public function __construct(
    private StateMachineRegistry $stateMachineRegistry
  ) {}

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
}
