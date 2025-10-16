<?php

namespace Credova\Storefront\Controller;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class CredovaCancelController extends AbstractController
{
    public function __construct(private readonly OrderTransactionStateHandler $transactionStateHandler)
    {
    }

    #[Route(path: '/credova/cancel/{orderTransactionId}/{orderId}/{token}', name: 'frontend.credova.cancel', methods: ['GET'])]
    public function cancel(string $orderTransactionId, string $orderId, string $token, Context $context): RedirectResponse
    {
        $expectedToken = $orderTransactionId . '-' . hash_hmac('sha256', $orderId, $_ENV['APP_SECRET']);

        if (!hash_equals($expectedToken, $token)) {
            throw $this->createAccessDeniedException('Invalid cancel token.');
        }

        $this->transactionStateHandler->fail($orderTransactionId, $context);

        return new RedirectResponse("/account/order/edit/{$orderId}?credovaCancel=true");
    }
}
