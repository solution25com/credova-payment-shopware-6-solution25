<?php

namespace Credova\PaymentMethods;

interface PaymentMethodIterface
{
  /**
   * Return name of the payment method.
   *
   * @return string
   */
    public function getName(): string;

  /**
   * Return the description of the payment method.
   *
   * @return string
   */
    public function getDescription(): string;

  /**
   * Return the payment handler of a plugin.
   *
   * @return string
   */
    public function getPaymentHandler(): string;
}
