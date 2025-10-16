<?php

namespace Credova\Gateways;

use Credova\Service\ConfigService;
use Credova\Service\Endpoints;
use Credova\Service\OrderTransactionMapper\OrderTransactionMapper;
use Credova\Service\PaymentClientApi;
use DateTime;
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
    public function __construct(private readonly OrderTransactionStateHandler $transactionStateHandler, private readonly PaymentClientApi $paymentClientApi, private readonly OrderTransactionMapper $orderTransactionMapper, private readonly ConfigService $configService)
    {
    }

    public function supports(PaymentHandlerType $type, string $paymentMethodId, Context $context): bool
    {
        return false;
    }

  /**
   * @throws \Exception
   */
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
        $birthday = $order->getOrderCustomer()->getCustomer()->getBirthday();
        $birthdayString = $birthday instanceof \DateTimeInterface ? $birthday->format('Y-m-d') : null;
        $response = [];

        $transactionId = $transaction->getOrderTransactionId();
        $customToken = $transaction->getOrderTransactionId() . '-' . hash_hmac('sha256', $order->getId(), $_ENV['APP_SECRET']);

        $returnUrlOnCancel = sprintf(
            '%s/credova/cancel/%s/%s/%s',
            $request->getSchemeAndHttpHost(),
            $transactionId,
            $order->getId(),
            $customToken
        );

        if ($this->isValidDOB($birthdayString)) {
            $body = [
            'storeCode' => $storeCode,
            'firstName' => $billingAddress->getFirstName(),
            'lastName' => $billingAddress->getLastName(),
            'dateOfBirth' => $birthdayString,
            'mobilePhone' => $billingAddress->getPhoneNumber(),
            'email' => $order->getOrderCustomer()->getEmail(),
            'referenceNumber' => $order->getOrderNumber(),
            'redirectUrl' => $transaction->getReturnUrl(),
            'cancelUrl' => $returnUrlOnCancel,

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
        }

        if (empty($response['publicId'])) {
            $this->transactionStateHandler->fail($transaction->getOrderTransactionId(), $context);
            throw new \Exception('Credova API returned an error while processing payment');
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
    {
    }

    private function isValidDOB(?string $dob): bool
    {
        if (!$dob) {
            return false;
        }

        try {
            $dobDate = new DateTime($dob);
            $now = new DateTime();
            $age = $now->diff($dobDate)->y;

            return $dobDate < $now && $age >= 18;
        } catch (\Exception $e) {
            return false;
        }
    }
}
