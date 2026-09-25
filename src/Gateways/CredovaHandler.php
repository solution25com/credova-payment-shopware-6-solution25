<?php

declare(strict_types=1);

namespace Credova\Gateways;

use Credova\Library\Constants\CredovaFields;
use Credova\Service\ConfigService;
use Credova\Service\Endpoints;
use Credova\Service\OrderTransactionMapper\OrderTransactionMapper;
use Credova\Service\PaymentClientApi;
use DateTime;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AbstractPaymentHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerType;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class CredovaHandler extends AbstractPaymentHandler
{
    public function __construct(
        private readonly OrderTransactionStateHandler $transactionStateHandler,
        private readonly PaymentClientApi $paymentClientApi,
        private readonly OrderTransactionMapper $orderTransactionMapper,
        private readonly ConfigService $configService,
        private readonly LoggerInterface $logger
    ) {
    }

    public function supports(PaymentHandlerType $type, string $paymentMethodId, Context $context): bool
    {
        return false;
    }

    public function pay(Request $request, PaymentTransactionStruct $transaction, Context $context, ?Struct $validateStruct): ?RedirectResponse
    {
        $salesChannelId = $request->attributes->get('sw-sales-channel-id');
        $orderTransaction = $this->orderTransactionMapper->getOrderTransactionsById($transaction->getOrderTransactionId(), $context);
        if ($orderTransaction === null) {
            $this->transactionStateHandler->fail($transaction->getOrderTransactionId(), $context);
            throw new \RuntimeException('Order transaction not found');
        }

        $order = $orderTransaction->getOrder();
        if ($order === null) {
            $this->transactionStateHandler->fail($transaction->getOrderTransactionId(), $context);
            throw new \RuntimeException('Order not found');
        }

        $billingAddress = $order->getBillingAddress();
        if ($billingAddress === null) {
            $this->transactionStateHandler->fail($transaction->getOrderTransactionId(), $context);
            throw new \RuntimeException('Billing address missing');
        }

        $birthdayString = $this->getBirthdayString($order);
        $stateShort = $this->getBillingStateShort($billingAddress);
        if ($stateShort === null) {
            $this->transactionStateHandler->fail($transaction->getOrderTransactionId(), $context);
            throw new \RuntimeException('Billing state missing');
        }

        if (!$this->isValidDOB($birthdayString)) {
            $this->transactionStateHandler->fail($transaction->getOrderTransactionId(), $context);
            throw new \RuntimeException('Invalid or underage date of birth');
        }

        $appSecret = $this->configService->getConfig('appSecret', $salesChannelId);
        $appSecret = $appSecret !== null && $appSecret !== '' ? (string) $appSecret : null;
        if ($appSecret === null) {
            $this->transactionStateHandler->fail($transaction->getOrderTransactionId(), $context);
            throw new \RuntimeException('Application secret not configured');
        }

        $cancelToken = $transaction->getOrderTransactionId() . '-' . hash_hmac('sha256', $order->getId(), $appSecret);
        $returnUrlOnCancel = sprintf(
            '%s/credova/cancel/%s/%s/%s',
            $request->getSchemeAndHttpHost(),
            $transaction->getOrderTransactionId(),
            $order->getId(),
            $cancelToken
        );

        $body = $this->buildApplicationBody($order, $billingAddress, $birthdayString, $stateShort, $transaction, $returnUrlOnCancel);
        $storeCode = $this->configService->getConfig('storeCode', $salesChannelId);
        $body['storeCode'] = $storeCode;
        $callbackUrl = Endpoints::callbackUrl($request->getSchemeAndHttpHost());

        $response = $this->paymentClientApi->createApplication($body, (string) $salesChannelId, $callbackUrl);

        if (!empty($response['error'])) {
            $this->logger->error('Credova API error', ['message' => $response['error']]);
            $this->transactionStateHandler->fail($transaction->getOrderTransactionId(), $context);
            throw new \RuntimeException('Credova API error: ' . $response['error']);
        }

        if (empty($response['publicId']) || empty($response['link'])) {
            $this->transactionStateHandler->fail($transaction->getOrderTransactionId(), $context);
            throw new \RuntimeException('Credova API returned an error while processing payment');
        }

        $this->orderTransactionMapper->setCredovaCustomFieldOnTransaction($transaction->getOrderTransactionId(), $context, [
            CredovaFields::ORDER_PUBLIC_ID => $response['publicId'],
        ]);

        return new RedirectResponse($response['link']);
    }

    public function finalize(Request $request, PaymentTransactionStruct $transaction, Context $context): void
    {
    }

    private function getBirthdayString(OrderEntity $order): ?string
    {
        $customer = $order->getOrderCustomer()?->getCustomer();
        $birthday = $customer?->getBirthday();
        return $birthday instanceof \DateTimeInterface ? $birthday->format('Y-m-d') : null;
    }

    private function getBillingStateShort(OrderAddressEntity $billingAddress): ?string
    {
        $stateFull = $billingAddress->getCountryState()?->getShortCode();
        if ($stateFull === null || $stateFull === '') {
            return null;
        }
        $parts = explode('-', $stateFull);
        return end($parts) ?: null;
    }

    private function buildApplicationBody(
        OrderEntity $order,
        OrderAddressEntity $billingAddress,
        string $birthdayString,
        string $stateShort,
        PaymentTransactionStruct $transaction,
        string $returnUrlOnCancel
    ): array {
        $orderCustomer = $order->getOrderCustomer();
        $body = [
            'firstName' => $billingAddress->getFirstName(),
            'lastName' => $billingAddress->getLastName(),
            'dateOfBirth' => $birthdayString,
            'mobilePhone' => $billingAddress->getPhoneNumber(),
            'email' => $orderCustomer?->getEmail() ?? '',
            'referenceNumber' => $order->getOrderNumber(),
            'redirectUrl' => $transaction->getReturnUrl(),
            'cancelUrl' => $returnUrlOnCancel,
            'address' => [
                'street' => $billingAddress->getStreet(),
                'city' => $billingAddress->getCity(),
                'state' => $stateShort,
                'zipCode' => $billingAddress->getZipCode(),
            ],
        ];
        $body['products'] = $this->buildProductLines($order);
        $shipping = (float) $order->getShippingTotal();
        $totalTax = $this->calculateOrderLineItemsTax($order);
        $body['products'][] = [
            'id' => CredovaFields::PRODUCT_ID_SHIPPING,
            'description' => CredovaFields::PRODUCT_DESCRIPTION_SHIPPING,
            'quantity' => '1',
            'value' => number_format($shipping, 2, '.', ''),
        ];
        $body['products'][] = [
            'id' => CredovaFields::PRODUCT_ID_TAX,
            'description' => CredovaFields::PRODUCT_DESCRIPTION_TAX,
            'quantity' => '1',
            'value' => number_format($totalTax, 2, '.', ''),
        ];
        return $body;
    }

    private function buildProductLines(OrderEntity $order): array
    {
        $products = [];
        foreach ($order->getLineItems() as $lineItem) {
            $price = $lineItem->getPrice();
            $taxAmount = 0.0;
            if ($price !== null) {
                $calculatedTaxes = $price->getCalculatedTaxes();
                $elements = $calculatedTaxes->getElements();
                if ($elements !== []) {
                    $first = reset($elements);
                    $taxAmount = (float) $first->getTax();
                }
            }
            $payload = $lineItem->getPayload() ?? [];
            $products[] = [
                'id' => $lineItem->getId(),
                'description' => $lineItem->getLabel(),
                'serialNumber' => $payload[CredovaFields::PAYLOAD_PRODUCT_NUMBER] ?? $lineItem->getId(),
                'quantity' => (string) $lineItem->getQuantity(),
                'value' => $lineItem->getTotalPrice(),
            ];
        }
        return $products;
    }

    private function calculateOrderLineItemsTax(OrderEntity $order): float
    {
        $totalTax = 0.0;
        foreach ($order->getLineItems() as $lineItem) {
            $price = $lineItem->getPrice();
            if ($price === null) {
                continue;
            }
            $calculatedTaxes = $price->getCalculatedTaxes();
            $elements = $calculatedTaxes->getElements();
            if ($elements !== []) {
                $first = reset($elements);
                $totalTax += (float) $first->getTax();
            }
        }
        return $totalTax;
    }

    private function isValidDOB(?string $dob): bool
    {
        if ($dob === null || $dob === '') {
            return false;
        }
        try {
            $dobDate = new DateTime($dob);
            $now = new DateTime();
            $age = $now->diff($dobDate)->y;
            return $dobDate < $now && $age >= 18;
        } catch (\Exception) {
            return false;
        }
    }
}
