<?php

declare(strict_types=1);

namespace Credova\Storefront\Controller;

use Credova\Service\ConfigService;
use Credova\Service\OrderTransactionMapper\OrderTransactionMapper;
use Credova\Service\PaymentTransactionStateHandler\CredovaTransactionStateHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class CredovaCancelController extends AbstractController
{
    public function __construct(
        private readonly CredovaTransactionStateHandler $transactionStateHandler,
        private readonly ConfigService $configService,
        private readonly OrderTransactionMapper $orderTransactionMapper
    ) {
    }

    #[Route(
        path: '/credova/cancel/{orderTransactionId}/{orderId}/{token}',
        name: 'frontend.credova.cancel',
        methods: ['GET']
    )]
    public function cancel(
        string $orderTransactionId,
        string $orderId,
        string $token,
        SalesChannelContext $salesChannelContext
    ): RedirectResponse {
        $context = $salesChannelContext->getContext();
        $secret = $this->configService->getConfig('appSecret', null);

        if ($secret === null || $secret === '') {
            throw $this->createAccessDeniedException('Application secret not configured.');
        }

        $expectedToken = $orderTransactionId . '-' . hash_hmac('sha256', $orderId, (string) $secret);
        if (!hash_equals($expectedToken, $token)) {
            throw $this->createAccessDeniedException('Invalid cancel token.');
        }

        $transaction = $this->orderTransactionMapper->getOrderTransactionsById($orderTransactionId, $context);
        if ($transaction === null || $transaction->getOrderId() !== $orderId) {
            throw $this->createAccessDeniedException('Order transaction mismatch.');
        }

        $currentCustomerId = $salesChannelContext->getCustomerId();
        $orderCustomerId = $transaction->getOrder()?->getOrderCustomer()?->getCustomerId();
        if ($currentCustomerId !== null && $orderCustomerId !== null && !hash_equals($orderCustomerId, $currentCustomerId)) {
            throw $this->createAccessDeniedException('Customer does not own this order.');
        }

        $this->transactionStateHandler->cancelOrderAndTransaction($orderId, $orderTransactionId, $context);
        $this->addFlash('danger', 'Your Credova application was cancelled.');

        return new RedirectResponse("/account/order/edit/{$orderId}?credovaCancel=true");
    }
}
