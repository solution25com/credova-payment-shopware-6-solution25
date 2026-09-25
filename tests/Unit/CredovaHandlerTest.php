<?php

declare(strict_types=1);

namespace Credova\Tests\Unit;

use Credova\Gateways\CredovaHandler;
use Credova\Library\Constants\CredovaFields;
use Credova\Service\ConfigService;
use Credova\Service\OrderTransactionMapper\OrderTransactionMapper;
use Credova\Service\PaymentClientApi;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerType;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateEntity;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @covers \Credova\Gateways\CredovaHandler
 */
class CredovaHandlerTest extends TestCase
{
    private const SALES_CHANNEL_ID = '0190a1b2c3d4e5f60718293a4b5c6d70';
    private const TRANSACTION_ID = '0190a1b2c3d4e5f60718293a4b5c6d71';
    private const ORDER_ID = '0190a1b2c3d4e5f60718293a4b5c6d72';
    private const APP_SECRET = 'app-secret';
    private const RETURN_URL = 'https://shop.test/payment/finalize-transaction?_sw_payment_token=abc';

    /** @var OrderTransactionStateHandler&MockObject */
    private OrderTransactionStateHandler $stateHandler;

    /** @var PaymentClientApi&MockObject */
    private PaymentClientApi $apiClient;

    /** @var OrderTransactionMapper&MockObject */
    private OrderTransactionMapper $mapper;

    /** @var array<string, mixed> */
    private array $config;

    private Context $context;

    private CredovaHandler $handler;

    protected function setUp(): void
    {
        $this->stateHandler = $this->createMock(OrderTransactionStateHandler::class);
        $this->apiClient = $this->createMock(PaymentClientApi::class);
        $this->mapper = $this->createMock(OrderTransactionMapper::class);
        $this->context = Context::createDefaultContext();
        $this->config = [
            'appSecret' => self::APP_SECRET,
            'storeCode' => 'STORE-1',
        ];

        $configService = $this->createMock(ConfigService::class);
        $configService->method('getConfig')->willReturnCallback(
            function (string $key, ?string $salesChannelId = null) {
                static::assertSame(self::SALES_CHANNEL_ID, $salesChannelId);

                return $this->config[$key] ?? null;
            }
        );

        $this->handler = new CredovaHandler(
            $this->stateHandler,
            $this->apiClient,
            $this->mapper,
            $configService,
            new NullLogger()
        );
    }

    public function testHandlerDoesNotSupportRecurringOrRefundCapabilities(): void
    {
        foreach ([PaymentHandlerType::RECURRING, PaymentHandlerType::REFUND] as $type) {
            static::assertFalse($this->handler->supports($type, 'payment-method-id', $this->context));
        }
    }

    public function testPayCreatesAnApplicationAndRedirectsToCredova(): void
    {
        $this->mapper->method('getOrderTransactionsById')->willReturn($this->transaction($this->order()));

        $this->apiClient->expects(static::once())
            ->method('createApplication')
            ->with(
                static::callback(function (array $body): bool {
                    static::assertSame('STORE-1', $body['storeCode']);
                    static::assertSame('1990-05-17', $body['dateOfBirth']);
                    static::assertSame('NY', $body['address']['state']);
                    static::assertSame('buyer@example.com', $body['email']);
                    static::assertSame('10042', $body['referenceNumber']);
                    static::assertSame(self::RETURN_URL, $body['redirectUrl']);

                    $expectedToken = self::TRANSACTION_ID . '-' . hash_hmac('sha256', self::ORDER_ID, self::APP_SECRET);
                    static::assertSame(
                        sprintf('https://shop.test/credova/cancel/%s/%s/%s', self::TRANSACTION_ID, self::ORDER_ID, $expectedToken),
                        $body['cancelUrl']
                    );

                    $productIds = array_column($body['products'], 'id');
                    static::assertSame(['line-1', 'line-2', CredovaFields::PRODUCT_ID_SHIPPING, CredovaFields::PRODUCT_ID_TAX], $productIds);
                    static::assertSame('SW-1', $body['products'][0]['serialNumber']);
                    static::assertSame('2', $body['products'][0]['quantity']);
                    static::assertSame('25.00', $body['products'][2]['value']);
                    static::assertSame('41.00', $body['products'][3]['value']);

                    return true;
                }),
                self::SALES_CHANNEL_ID,
                'https://shop.test/credova/webhook'
            )
            ->willReturn(['publicId' => 'pub-123', 'link' => 'https://sandbox.credova.com/apply/pub-123']);

        $this->mapper->expects(static::once())
            ->method('setCredovaCustomFieldOnTransaction')
            ->with(self::TRANSACTION_ID, $this->context, [CredovaFields::ORDER_PUBLIC_ID => 'pub-123']);

        $this->stateHandler->expects(static::never())->method('fail');

        $response = $this->handler->pay($this->request(), $this->paymentTransaction(), $this->context, null);

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('https://sandbox.credova.com/apply/pub-123', $response->getTargetUrl());
    }

    public function testPayFailsTheTransactionForAnUnderageCustomer(): void
    {
        $order = $this->order();
        $order->getOrderCustomer()?->getCustomer()?->setBirthday((new \DateTime())->modify('-17 years'));
        $this->mapper->method('getOrderTransactionsById')->willReturn($this->transaction($order));

        $this->assertPayFails('Invalid or underage date of birth');
    }

    public function testPayFailsTheTransactionWithoutABirthday(): void
    {
        $order = $this->order();
        $order->getOrderCustomer()?->getCustomer()?->setBirthday(null);
        $this->mapper->method('getOrderTransactionsById')->willReturn($this->transaction($order));

        $this->assertPayFails('Invalid or underage date of birth');
    }

    public function testPayFailsTheTransactionWithoutABillingState(): void
    {
        $order = $this->order();
        $order->getBillingAddress()?->setCountryStateId(null);
        $order->getBillingAddress()?->assign(['countryState' => null]);
        $this->mapper->method('getOrderTransactionsById')->willReturn($this->transaction($order));

        $this->assertPayFails('Billing state missing');
    }

    public function testPayFailsTheTransactionWhenTheAppSecretIsMissing(): void
    {
        $this->config['appSecret'] = '';
        $this->mapper->method('getOrderTransactionsById')->willReturn($this->transaction($this->order()));

        $this->assertPayFails('Application secret not configured');
    }

    public function testPayFailsTheTransactionWhenCredovaReturnsAnError(): void
    {
        $this->mapper->method('getOrderTransactionsById')->willReturn($this->transaction($this->order()));
        $this->apiClient->method('createApplication')->willReturn(['error' => 'Authentication failed']);

        $this->assertPayFails('Credova API error: Authentication failed');
    }

    public function testPayFailsTheTransactionWhenCredovaReturnsNoLink(): void
    {
        $this->mapper->method('getOrderTransactionsById')->willReturn($this->transaction($this->order()));
        $this->apiClient->method('createApplication')->willReturn(['publicId' => 'pub-123']);

        $this->assertPayFails('Credova API returned an error while processing payment');
    }

    public function testPayFailsTheTransactionWhenItCannotBeLoaded(): void
    {
        $this->mapper->method('getOrderTransactionsById')->willReturn(null);

        $this->assertPayFails('Order transaction not found');
    }

    private function assertPayFails(string $message): void
    {
        $this->stateHandler->expects(static::once())
            ->method('fail')
            ->with(self::TRANSACTION_ID, $this->context);
        $this->mapper->expects(static::never())->method('setCredovaCustomFieldOnTransaction');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($message);

        $this->handler->pay($this->request(), $this->paymentTransaction(), $this->context, null);
    }

    private function request(): Request
    {
        $request = Request::create('https://shop.test/checkout/order', 'POST');
        $request->attributes->set('sw-sales-channel-id', self::SALES_CHANNEL_ID);

        return $request;
    }

    private function paymentTransaction(): PaymentTransactionStruct
    {
        return new PaymentTransactionStruct(self::TRANSACTION_ID, self::RETURN_URL);
    }

    private function transaction(OrderEntity $order): OrderTransactionEntity
    {
        $transaction = new OrderTransactionEntity();
        $transaction->setId(self::TRANSACTION_ID);
        $transaction->setOrderId(self::ORDER_ID);
        $transaction->setOrder($order);

        return $transaction;
    }

    private function order(): OrderEntity
    {
        $customer = new CustomerEntity();
        $customer->setId('customer-1');
        $customer->setBirthday(new \DateTime('1990-05-17'));

        $orderCustomer = new OrderCustomerEntity();
        $orderCustomer->setEmail('buyer@example.com');
        $orderCustomer->setCustomer($customer);

        $state = new CountryStateEntity();
        $state->setId('state-1');
        $state->setShortCode('US-NY');

        $billingAddress = new OrderAddressEntity();
        $billingAddress->setId('address-1');
        $billingAddress->setFirstName('Jane');
        $billingAddress->setLastName('Doe');
        $billingAddress->setStreet('1 Main Street');
        $billingAddress->setCity('New York');
        $billingAddress->setZipcode('10001');
        $billingAddress->setPhoneNumber('2125550100');
        $billingAddress->setCountryStateId('state-1');
        $billingAddress->setCountryState($state);

        $order = new OrderEntity();
        $order->setId(self::ORDER_ID);
        $order->setOrderNumber('10042');
        $order->setShippingTotal(25.0);
        $order->setBillingAddress($billingAddress);
        $order->setOrderCustomer($orderCustomer);
        $order->setLineItems(new OrderLineItemCollection([
            $this->lineItem('line-1', 'Rifle scope', 2, 400.0, 32.0, ['productNumber' => 'SW-1']),
            $this->lineItem('line-2', 'Cleaning kit', 1, 100.0, 9.0, []),
        ]));

        return $order;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function lineItem(string $id, string $label, int $quantity, float $total, float $tax, array $payload): OrderLineItemEntity
    {
        $lineItem = new OrderLineItemEntity();
        $lineItem->setId($id);
        $lineItem->setLabel($label);
        $lineItem->setQuantity($quantity);
        $lineItem->setTotalPrice($total);
        $lineItem->setPayload($payload);
        $lineItem->setPrice(new CalculatedPrice(
            $total / $quantity,
            $total,
            new CalculatedTaxCollection([new CalculatedTax($tax, 8.0, $total)]),
            new TaxRuleCollection(),
            $quantity
        ));

        return $lineItem;
    }
}
