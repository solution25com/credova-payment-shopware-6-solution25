<?php

namespace Credova\PaymentMethods;

use Credova\Gateways\CredovaHandler;

class CredovaPaymentMethod
{
  /**
   * @inheritDoc
   */
    public function getName(): string
    {
        return 'Credova Payment';
    }

  /**
   * @inheritDoc
   */
    public function getDescription(): string
    {
        return 'Credova buy now Pay later';
    }

  /**
   * @inheritDoc
   */
    public function getHandlerIdentifier(): string
    {
        return CredovaHandler::class;
    }

    public function getTechnicalName(): string
    {
        return 'credova_payment';
    }
}
