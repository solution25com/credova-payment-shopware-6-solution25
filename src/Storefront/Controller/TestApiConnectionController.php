<?php

declare(strict_types=1);

namespace Credova\Storefront\Controller;

use Credova\Service\TestConnectionService;
use Shopware\Core\Framework\Context;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
class TestApiConnectionController extends StorefrontController
{
    public function __construct(
        private readonly TestConnectionService $testConnectionService,
    ) {
    }

    #[Route(
        path: '/api/_action/credova-test-connection/test-connection',
        name: 'api.action.credova.test-connection',
        methods: ['POST']
    )]
    public function testConnection(Context $context): JsonResponse
    {
        $results = $this->testConnectionService->testAllConnections($context);
        $allConnectionsValid = !in_array(false, $results, true);

        return new JsonResponse([
        'success' => $allConnectionsValid,
        'details' => $results,
        ]);
    }
}
