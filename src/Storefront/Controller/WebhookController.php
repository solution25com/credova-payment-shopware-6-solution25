<?php

declare(strict_types=1);

namespace Credova\Storefront\Controller;

use Credova\Library\Constants\CredovaFields;
use Credova\Service\ConfigService;
use Credova\Service\OrderTransactionMapper\OrderTransactionMapper;
use Credova\Service\Webhook\WebhookStateTransitionGuard;
use Credova\Service\PaymentClientApi;
use Credova\Service\PaymentTransactionStateHandler\CredovaTransactionStateHandler;
use JsonException;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['storefront'], 'csrf_protected' => false])]
class WebhookController extends StorefrontController
{
    private const STATUS_TO_ACTION = [
        'Approved' => 'approved',
        'Signed' => 'signed',
        'Funded' => 'paid',
        'Declined' => 'fail',
        'Returned' => 'cancel',
    ];

    public function __construct(
        private readonly OrderTransactionStateHandler $transactionStateHandler,
        private readonly CredovaTransactionStateHandler $credovaTransactionStateHandler,
        private readonly OrderTransactionMapper $orderTransactionMapper,
        private readonly PaymentClientApi $paymentClientApi,
        private readonly ConfigService $configService,
        private readonly LoggerInterface $logger
    ) {
    }

    #[Route(
        path: '/credova/webhook',
        name: 'frontend.webhook.webhook',
        methods: ['POST'],
        defaults: ['csrf_protected' => false]
    )]
    public function webhook(Request $request, SalesChannelContext $context): Response
    {
        $payload = $this->parsePayload($request->getContent());
        if ($payload === null) {
            return $this->respond(false, 'Invalid JSON payload');
        }

        if (!$this->validatePayloadStructure($payload)) {
            $this->logger->warning('Credova webhook invalid payload structure');
            return $this->respond(false, 'Invalid payload');
        }

        $publicId = $payload['publicId'];
        $status = $payload['status'] ?? null;

        $swContext = $context->getContext();
        $transaction = $this->orderTransactionMapper->findOrderTransactionByCredovaPublicId($publicId, $swContext);

        // Backward compatibility for orders created before transaction-level storage.
        if ($transaction === null) {
            $legacyOrder = $this->orderTransactionMapper->findOrderByCredovaPublicId($publicId, $swContext);
            if ($legacyOrder !== null) {
                $transaction = $this->orderTransactionMapper->findLatestCredovaOrderTransactionByOrderId($legacyOrder->getId(), $swContext);
                if ($transaction !== null) {
                    $this->orderTransactionMapper->setCredovaCustomFieldOnTransaction($transaction->getId(), $swContext, [
                        CredovaFields::ORDER_PUBLIC_ID => $publicId,
                    ]);
                }
            }
        }

        if ($transaction === null) {
            $this->logger->info('Credova webhook order not found', ['publicId' => $publicId]);
            return $this->respond(false, 'Order not found for publicId');
        }

        $transactionId = $transaction->getId();
        $order = $transaction->getOrder();
        if ($order === null) {
            return $this->respond(false, 'Order has no transactions');
        }

        $eventId = $publicId . '|' . $status;
        if ($this->isDuplicateWebhookEvent($transaction, $eventId)) {
            $this->logger->info('Credova webhook duplicate ignored', ['publicId' => $publicId]);
            return $this->respond(true, 'Webhook already processed');
        }

        try {
            $this->orderTransactionMapper->updateCredovaFieldsFromWebhook($transactionId, $swContext, $payload);
            $this->orderTransactionMapper->updateCredovaOrderFieldsFromWebhook($order, $swContext, $payload);
            $this->orderTransactionMapper->updateCredovaCustomer($order, $swContext, $payload);

            $actionMethod = self::STATUS_TO_ACTION[$status] ?? null;
            $stateName = $transaction->getStateMachineState()?->getTechnicalName() ?? '';
            if ($actionMethod !== null && !WebhookStateTransitionGuard::isTransitionAllowed($stateName, $actionMethod)) {
                $this->logger->info('Credova webhook state transition not allowed', ['publicId' => $publicId, 'status' => $status]);
                return $this->respond(true, 'No state change');
            }

            $finalPaymentState = $this->configService->getConfig('finalTransactionState', '');

            $myAction = $actionMethod === 'signed' ? $finalPaymentState : $actionMethod;

            match ($myAction) {
                'approved' => $this->credovaTransactionStateHandler->credovaApproved($transactionId, $swContext),
                'signed' => $this->credovaTransactionStateHandler->credovaSigned($transactionId, $swContext),
                'paid' => $this->transactionStateHandler->paid($transactionId, $swContext),
                'fail' => $this->transactionStateHandler->fail($transactionId, $swContext),
                'cancel' => $this->transactionStateHandler->cancel($transactionId, $swContext),
                default => null,
            };

            $this->orderTransactionMapper->setTransactionWebhookEventId($transactionId, $eventId, $swContext);

            if ($actionMethod === 'signed') {
                $this->handleSignedDeliveryAndReferences($order, $publicId);
            }

            return $this->respond(true, 'Webhook processed');
        } catch (\Throwable $e) {
            $this->logger->error('Credova webhook processing failed', [
                'error' => $e->getMessage(),
                'publicId' => $publicId,
            ]);
            return $this->respond(false, 'Webhook processing failed');
        }
    }

    private function parsePayload(string $content): ?array
    {
        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : null;
        } catch (JsonException) {
            return null;
        }
    }

    private function validatePayloadStructure(array $payload): bool
    {
        return isset($payload['publicId']) && is_string($payload['publicId']) && $payload['publicId'] !== '';
    }

    private function isDuplicateWebhookEvent(OrderTransactionEntity $transaction, string $eventId): bool
    {
        $customFields = $transaction->getCustomFields() ?? [];
        $stored = $customFields[CredovaFields::TRANSACTION_WEBHOOK_EVENT_ID] ?? null;
        return $stored === $eventId;
    }

    private function handleSignedDeliveryAndReferences(OrderEntity $order, string $publicId): void
    {
        $orderDelivery = $order->getDeliveries()?->first();
        $shippingAddress = $orderDelivery?->getShippingOrderAddress();
        if ($shippingAddress !== null) {
            $stateFull = $shippingAddress->getCountryState()?->getShortCode() ?? '';
            $parts = explode('-', $stateFull);
            $stateShort = end($parts);
            $this->paymentClientApi->addDeliveryInformation($publicId, [
                'address' => $shippingAddress->getStreet(),
                'city' => $shippingAddress->getCity(),
                'state' => $stateShort,
                'zip' => $shippingAddress->getZipCode(),
            ]);
        }
        $this->paymentClientApi->addReferencesToOrder($publicId, [
            'orders' => [$order->getOrderNumber()],
        ]);
    }

    private function respond(bool $ok, string $message): JsonResponse
    {
        return new JsonResponse(
            ['success' => $ok, 'message' => $message],
            Response::HTTP_OK
        );
    }
}
