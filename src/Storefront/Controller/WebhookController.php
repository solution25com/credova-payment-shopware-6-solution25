<?php declare(strict_types=1);

namespace Credova\Storefront\Controller;

use Credova\Service\OrderTransactionMapper\OrderTransactionMapper;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use JsonException;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class WebhookController extends StorefrontController
{
  private const STATUS_TO_ACTION = [
    'Approved' => 'paid',
    'Signed' => 'paid',
    'Funded' => 'paid',
    'Declined' => 'fail',
    'Returned' => 'cancel',
  ];

  public function __construct(
    private readonly OrderTransactionStateHandler $transactionStateHandler,
    private readonly OrderTransactionMapper       $orderTransactionMapper,
    private readonly LoggerInterface              $logger
  )
  {}

  #[Route(
    path: '/credova/webhook',
    name: 'frontend.webhook.webhook',
    methods: ['POST']
  )]
  public function webhook(Request $request, SalesChannelContext $context): Response
  {
    $swContext = $context->getContext();

    $payload = $this->parsePayload($request);
    if ($payload === null) {
      return $this->respond(false, 'Invalid JSON payload');
    }

    $publicId = $payload['publicId'] ?? null;
    if ($publicId === null || $publicId === '') {
      $this->logger->warning('Credova webhook missing publicId', ['payload' => $payload]);
      return $this->respond(false, 'Missing publicId');
    }

    try {
      $order = $this->orderTransactionMapper->findOrderByCredovaPublicId($publicId, $swContext);
      if ($order === null) {
        $this->logger->warning('Credova webhook could not match order', [
          'publicId' => $publicId,
          'payload' => $payload,
        ]);
        return $this->respond(false, 'Order not found for publicId');
      }

      $this->orderTransactionMapper->updateCredovaFieldsFromWebhook($order, $swContext, $payload);

      $transactionId = $this->getFirstTransactionId($order);
      if ($transactionId === null) {
        $this->logger->warning('Credova webhook: order has no transactions', [
          'orderId' => $order->getId(),
          'payload' => $payload,
        ]);
        return $this->respond(false, 'Order has no transactions');
      }

      $status = $payload['status'] ?? null;
      $actionMethod = $status !== null ? (self::STATUS_TO_ACTION[$status] ?? null) : null;

      if ($actionMethod !== null) {
        $this->transactionStateHandler->{$actionMethod}($transactionId, $swContext);
      } else {
        $this->logger->info('Credova webhook received unhandled status', [
          'status' => $status,
          'payload' => $payload,
        ]);
      }

      return $this->respond(true, 'Webhook processed');
    } catch (\Throwable $e) {
      $this->logger->error('Credova webhook processing failed', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'payload' => $payload,
      ]);
      return $this->respond(false, 'Webhook processing failed');
    }
  }

  private function parsePayload(Request $request): ?array
  {
    try {
      /** @var mixed $decoded */
      $decoded = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
      return is_array($decoded) ? $decoded : null;
    } catch (JsonException) {
      return null;
    }
  }

  private function getFirstTransactionId(OrderEntity $order): ?string
  {
    return $order->getTransactions()?->first()?->getId();
  }

  private function respond(bool $ok, string $message): JsonResponse
  {
    // Always return 200 OK as required by Credova
    return new JsonResponse(
      ['success' => $ok, 'message' => $message],
      Response::HTTP_OK
    );
  }

}