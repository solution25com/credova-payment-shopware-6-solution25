<?php

namespace Credova\Gateways;

use Credova\Service\ConfigService;
use Credova\Service\Endpoints;
use Credova\Service\OrderTransactionMapper\OrderTransactionMapper;
use Credova\Service\PaymentClientApi;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AbstractPaymentHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerType;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class CredovaHandler extends AbstractPaymentHandler
{

  public function __construct(private readonly OrderTransactionStateHandler $transactionStateHandler, PaymentClientApi $paymentClientApi, OrderTransactionMapper $orderTransactionMapper, ConfigService $configService)
  {
    $this->paymentClientApi = $paymentClientApi;
    $this->orderTransactionMapper = $orderTransactionMapper;
    $this->configService = $configService;
  }

  public function supports(PaymentHandlerType $type, string $paymentMethodId, Context $context): bool
  {
    return false;
  }

  public function pay(Request $request, PaymentTransactionStruct $transaction, Context $context, ?Struct $validateStruct): ?RedirectResponse
  {

    $salesChannelId = $request->attributes->get('sw-sales-channel-id');
    $storeCode = $this->configService->getConfig('storeCode', $salesChannelId);
    $order = $this->orderTransactionMapper->getOrderTransactionsById($transaction->getOrderTransactionId(), $context)->getOrder();
    $callbackUrl = Endpoints::callbackUrl($request->getSchemeAndHttpHost());
    $billingAddress = $order->getBillingAddress();
    $stateFull = $billingAddress->getCountryState()->getShortCode();
    $parts = explode('-', $stateFull);
    $stateShort = end($parts);

    $body = [
      'storeCode' => $storeCode,
      'firstName' => $billingAddress->getFirstName(),
      'lastName' => $billingAddress->getLastName(),
      'dateOfBirth' => '1983-04-01', // todo: default + 18 for now
      'mobilePhone' => $billingAddress->getPhoneNumber(),
      'email' => $order->getOrderCustomer()->getEmail(),
      'referenceNumber' => $order->getOrderNumber(),
      'redirectUrl' => $transaction->getReturnUrl(),
      'cancelUrl' => $request->getSchemeAndHttpHost(),

      'address' => [
        'street' => $billingAddress->getStreet(),
        'city' => $billingAddress->getCity(),
        'state' => $stateShort,
        'zipCode' => $billingAddress->getZipCode(),
      ],

      'products' => []
    ];

    foreach ($order->getLineItems() as $lineItem) {
      $body['products'][] = [
        'id' => $lineItem->getId(),
        'description' => $lineItem->getLabel(),
        'serialNumber' => $lineItem->getPayload()['productNumber'] ?? $lineItem->getId(),
        'quantity' => (string)$lineItem->getQuantity(),
        'value' => number_format($lineItem->getTotalPrice(), 2, '.', '')
      ];
    }

    $response = $this->paymentClientApi->createApplication($body, $salesChannelId, $callbackUrl);

    if (!$response['publicId']) {
      $this->transactionStateHandler->fail($transaction->getOrderTransactionId(), $context);
      throw new \RuntimeException('Credova API returned an error while processing payment');
    }

    $this->orderTransactionMapper->setCredovaCustomFieldFromOrder(
      $order,
      $context,
      [
        'credovaPublicId' => $response['publicId'],
      ]
    );

    return new RedirectResponse($response['link']);
  }

  public function finalize(Request $request, PaymentTransactionStruct $transaction, Context $context): void
  {}
}