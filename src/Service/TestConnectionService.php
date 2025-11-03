<?php

declare(strict_types=1);

namespace Credova\Service;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

readonly class TestConnectionService
{
    public function __construct(
        private PaymentClientApi $paymentClientApi,
        private EntityRepository $salesChannelRepository,
    ) {
    }

  public function testAllConnections(Context $context): array|bool
  {
    $salesChannels = $this->salesChannelRepository->search(new Criteria(), $context);
    $results = [];

    if (!$salesChannels->getEntities() instanceof SalesChannelCollection) {
      return false;
    }

    /** @var SalesChannelEntity $salesChannel */
    foreach ($salesChannels as $salesChannel) {
      $id = $salesChannel->getId();
      $name = $salesChannel->getTranslation('name') ?? $salesChannel->getName();

      try {
        $results[$name] = $this->paymentClientApi->testConnection($id);
      } catch (\Throwable $e) {
        $results[$name] = false;
      }
    }

    return $results;
  }}
