<?php

declare(strict_types=1);

namespace Credova\Tests\Unit;

use Credova\Library\Constants\CredovaFields;
use Credova\Service\ConfigService;
use Credova\Service\OrderTransactionMapper\OrderTransactionMapper;
use Credova\Service\PaymentClientApi;
use Credova\Service\PaymentTransactionStateHandler\CredovaTransactionStateHandler;
use Credova\Storefront\Controller\WebhookController;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @covers \Credova\Storefront\Controller\WebhookController
 * @covers \Credova\Service\PaymentTransactionStateHandler\CredovaTransactionStateHandler
 */
class WebhookControllerTest extends TestCase
{
    private const TRANSACTION_ID = '0190a1b2c3d4e5f60718293a4b5c6d71';
    private const ORDER_ID = '0190a1b2c3d4e5f60718293a4b5c6d72';
    private const PUBLIC_ID = 'pub-123';

    /** @var OrderTransactionStateHandler&MockObject */
    private OrderTransactionStateHandler $stateHandler;

    /** @var StateMachineRegistry&MockObject */
    private StateMachineRegistry $stateMachineRegistry;

    /** @var OrderTransactionMapper&MockObject */
    private OrderTransactionMapper $mapper;

    /** @var PaymentClientApi&MockObject */
    private PaymentClientApi $apiClient;

    /** @var SalesChannelContext&MockObject */
    private SalesChannelContext $salesChannelContext;

    private Context $context;

    private WebhookController $controller;

    protected function setUp(): void
    {
        $this->stateHandler = $this->createMock(OrderTransactionStateHandler::class);
        $this->stateMachineRegistry = $this->createMock(StateMachineRegistry::class);
        $this->mapper = $this->createMock(OrderTransactionMapper::class);
        $this->apiClient = $this->createMock(PaymentClientApi::class);
        $this->context = Context::createDefaultContext();

        $this->salesChannelContext = $this->createMock(SalesChannelContext::class);
        $this->salesChannelContext->method('getContext')->willReturn($this->context);

        $configService = $this->createMock(ConfigService::class);
        $configService->method('getConfig')->willReturnMap([
            ['finalTransactionState', '', 'paid'],
        ]);

        $this->controller = new WebhookController(
            $this->stateHandler,
            new CredovaTransactionStateHandler($this->stateMachineRegistry, $this->stateHandler),
            $this->mapper,
            $this->apiClient,
            $configService,
            new NullLogger()
        );
    }

    public function testApprovedMovesAnOpenTransactionToCredovaApproved(): void
    {
        $this->givenTransaction('open');
        $this->expectCredovaTransition('credova_approved');
        $this->mapper->expects(static::once())
            ->method('setTransactionWebhookEventId')
            ->with(self::TRANSACTION_ID, self::PUBLIC_ID . '|Approved', $this->context);

        $this->assertResponse($this->send(['publicId' => self::PUBLIC_ID, 'status' => 'Approved']), true, 'Webhook processed');
    }

    public function testSignedAppliesTheConfiguredFinalStateAndReportsTheDelivery(): void
    {
        $this->givenTransaction('credova_approved');
        $this->stateHandler->expects(static::once())->method('paid')->with(self::TRANSACTION_ID, $this->context);
        $this->apiClient->expects(static::once())
            ->method('addDeliveryInformation')
            ->with(self::PUBLIC_ID, [
                'address' => '1 Main Street',
                'city' => 'New York',
                'state' => 'NY',
                'zip' => '10001',
            ]);
        $this->apiClient->expects(static::once())
            ->method('addReferencesToOrder')
            ->with(self::PUBLIC_ID, ['orders' => ['10042']]);

        $this->assertResponse($this->send(['publicId' => self::PUBLIC_ID, 'status' => 'Signed']), true, 'Webhook processed');
    }

    public function testFundedMarksASignedTransactionAsPaid(): void
    {
        $this->givenTransaction('credova_signed');
        $this->stateHandler->expects(static::once())->method('paid')->with(self::TRANSACTION_ID, $this->context);

        $this->assertResponse($this->send(['publicId' => self::PUBLIC_ID, 'status' => 'Funded']), true, 'Webhook processed');
    }

    public function testDeclinedFailsTheTransaction(): void
    {
        $this->givenTransaction('credova_approved');
        $this->stateHandler->expects(static::once())->method('fail')->with(self::TRANSACTION_ID, $this->context);

        $this->assertResponse($this->send(['publicId' => self::PUBLIC_ID, 'status' => 'Declined']), true, 'Webhook processed');
    }

    public function testReturnedCancelsTheTransaction(): void
    {
        $this->givenTransaction('credova_signed');
        $this->stateHandler->expects(static::once())->method('cancel')->with(self::TRANSACTION_ID, $this->context);

        $this->assertResponse($this->send(['publicId' => self::PUBLIC_ID, 'status' => 'Returned']), true, 'Webhook processed');
    }

    public function testAStatusThatIsNotAllowedFromTheCurrentStateChangesNothing(): void
    {
        $this->givenTransaction('paid');
        $this->stateMachineRegistry->expects(static::never())->method('transition');
        $this->mapper->expects(static::never())->method('setTransactionWebhookEventId');

        $this->assertResponse($this->send(['publicId' => self::PUBLIC_ID, 'status' => 'Approved']), true, 'No state change');
    }

    public function testADuplicateEventIsIgnored(): void
    {
        $this->givenTransaction('credova_approved', [CredovaFields::TRANSACTION_WEBHOOK_EVENT_ID => self::PUBLIC_ID . '|Approved']);
        $this->mapper->expects(static::never())->method('updateCredovaFieldsFromWebhook');
        $this->stateMachineRegistry->expects(static::never())->method('transition');

        $this->assertResponse($this->send(['publicId' => self::PUBLIC_ID, 'status' => 'Approved']), true, 'Webhook already processed');
    }

    public function testAnUnknownPublicIdIsRejected(): void
    {
        $this->mapper->method('findOrderTransactionByCredovaPublicId')->willReturn(null);
        $this->mapper->method('findOrderByCredovaPublicId')->willReturn(null);
        $this->mapper->expects(static::never())->method('updateCredovaFieldsFromWebhook');

        $this->assertResponse($this->send(['publicId' => 'unknown', 'status' => 'Approved']), false, 'Order not found for publicId');
    }

    public function testAPayloadWithoutPublicIdIsRejected(): void
    {
        $this->mapper->expects(static::never())->method('findOrderTransactionByCredovaPublicId');

        $this->assertResponse($this->send(['status' => 'Approved']), false, 'Invalid payload');
    }

    public function testInvalidJsonIsRejected(): void
    {
        $request = Request::create('/credova/webhook', 'POST', [], [], [], [], '{not json');

        $this->assertResponse($this->controller->webhook($request, $this->salesChannelContext), false, 'Invalid JSON payload');
    }

    public function testALegacyOrderLevelPublicIdIsMigratedToTheTransaction(): void
    {
        $transaction = $this->transaction('open', []);
        $this->mapper->method('findOrderTransactionByCredovaPublicId')->willReturn(null);
        $this->mapper->method('findOrderByCredovaPublicId')->willReturn($transaction->getOrder());
        $this->mapper->method('findLatestCredovaOrderTransactionByOrderId')->with(self::ORDER_ID)->willReturn($transaction);
        $this->mapper->expects(static::once())
            ->method('setCredovaCustomFieldOnTransaction')
            ->with(self::TRANSACTION_ID, $this->context, [CredovaFields::ORDER_PUBLIC_ID => self::PUBLIC_ID]);
        $this->expectCredovaTransition('credova_approved');

        $this->assertResponse($this->send(['publicId' => self::PUBLIC_ID, 'status' => 'Approved']), true, 'Webhook processed');
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function send(array $payload): Response
    {
        $request = Request::create('/credova/webhook', 'POST', [], [], [], [], (string) json_encode($payload));

        return $this->controller->webhook($request, $this->salesChannelContext);
    }

    private function assertResponse(Response $response, bool $success, string $message): void
    {
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame(
            ['success' => $success, 'message' => $message],
            json_decode((string) $response->getContent(), true)
        );
    }

    private function expectCredovaTransition(string $action): void
    {
        $this->stateMachineRegistry->expects(static::once())
            ->method('transition')
            ->with(
                static::callback(static function (Transition $transition) use ($action): bool {
                    static::assertSame('order_transaction', $transition->getEntityName());
                    static::assertSame(self::TRANSACTION_ID, $transition->getEntityId());
                    static::assertSame($action, $transition->getTransitionName());

                    return true;
                }),
                $this->context
            );
    }

    /**
     * @param array<string, mixed> $customFields
     */
    private function givenTransaction(string $state, array $customFields = []): void
    {
        $this->mapper->method('findOrderTransactionByCredovaPublicId')
            ->with(self::PUBLIC_ID, $this->context)
            ->willReturn($this->transaction($state, $customFields));
    }

    /**
     * @param array<string, mixed> $customFields
     */
    private function transaction(string $state, array $customFields): OrderTransactionEntity
    {
        $countryState = new CountryStateEntity();
        $countryState->setId('state-1');
        $countryState->setShortCode('US-NY');

        $shippingAddress = new OrderAddressEntity();
        $shippingAddress->setId('address-1');
        $shippingAddress->setStreet('1 Main Street');
        $shippingAddress->setCity('New York');
        $shippingAddress->setZipcode('10001');
        $shippingAddress->setCountryState($countryState);

        $delivery = new OrderDeliveryEntity();
        $delivery->setId('delivery-1');
        $delivery->setShippingOrderAddress($shippingAddress);

        $order = new OrderEntity();
        $order->setId(self::ORDER_ID);
        $order->setOrderNumber('10042');
        $order->setDeliveries(new OrderDeliveryCollection([$delivery]));

        $stateMachineState = new StateMachineStateEntity();
        $stateMachineState->setId('state-machine-state-1');
        $stateMachineState->setTechnicalName($state);

        $transaction = new OrderTransactionEntity();
        $transaction->setId(self::TRANSACTION_ID);
        $transaction->setOrderId(self::ORDER_ID);
        $transaction->setOrder($order);
        $transaction->setStateMachineState($stateMachineState);
        $transaction->setCustomFields($customFields);

        return $transaction;
    }
}
