<?php declare(strict_types=1);

namespace Credova\Storefront\Controller;

use Credova\Service\OrderTransactionMapper\OrderTransactionMapper;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class WebhookController extends StorefrontController
{
  private OrderTransactionStateHandler $transactionStateHandler;
  private OrderTransactionMapper $orderTransactionMapper;
  private LoggerInterface $logger;

  public function __construct(
    OrderTransactionStateHandler $transactionStateHandler,
    OrderTransactionMapper $orderTransactionMapper,
    LoggerInterface $logger
  ) {
    $this->transactionStateHandler = $transactionStateHandler;
    $this->orderTransactionMapper = $orderTransactionMapper;
    $this->logger = $logger;
  }

  #[Route(
    path: '/credova/webhook',
    name: 'frontend.webhook.webhook',
    methods: ['POST']
  )]
  public function webhook(Request $request, SalesChannelContext $context): Response
  {
    $payload = json_decode($request->getContent(), true);

    if (empty($payload['publicId'])) {
      $this->logger->warning('Credova webhook missing publicId', [
        'payload' => $payload
      ]);

      return new JsonResponse([
        'success' => false,
        'message' => 'Missing publicId'
      ], Response::HTTP_OK);
    }

    try {
      $order = $this->orderTransactionMapper->findOrderByCredovaPublicId(
        $payload['publicId'],
        $context->getContext()
      );

      if (!$order) {
        $this->logger->warning('Credova webhook could not match order', [
          'publicId' => $payload['publicId'],
          'payload' => $payload
        ]);

        return new JsonResponse([
          'success' => false,
          'message' => 'Order not found for publicId',
        ], Response::HTTP_OK);
      }

      $this->orderTransactionMapper->updateCredovaFieldsFromWebhook(
        $order,
        $context->getContext(),
        $payload
      );

      $transactionId = $order->getTransactions()->first()->getId();
      $status = $payload['status'] ?? null;

      switch ($status) {
        case 'Approved':
        case 'Signed':
        $this->transactionStateHandler->paid($transactionId, $context->getContext());
//        $this->transactionStateHandler->process($transactionId, $context->getContext());
          break;

        case 'Funded':
          $this->transactionStateHandler->paid($transactionId, $context->getContext());
          break;

        case 'Declined':
          $this->transactionStateHandler->fail($transactionId, $context->getContext());
          break;

        case 'Returned':
          $this->transactionStateHandler->cancel($transactionId, $context->getContext());
          break;

        default:
          $this->logger->info('Credova webhook received unhandled status', [
            'status' => $status,
            'payload' => $payload
          ]);
          break;
      }

      return new JsonResponse([
        'success' => true,
        'message' => 'Webhook processed successfully',
        'status' => $status,
      ], Response::HTTP_OK);

    } catch (\Throwable $e) {
      $this->logger->error('Credova webhook processing failed', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'payload' => $payload
      ]);

      return new JsonResponse([
        'success' => false,
        'message' => 'Internal error while processing webhook',
      ], Response::HTTP_OK);
    }
  }
}
