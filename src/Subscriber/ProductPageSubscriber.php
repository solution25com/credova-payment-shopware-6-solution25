<?php

namespace Credova\Subscriber;

use Credova\Service\ConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelEntityLoadedEvent;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;

class ProductPageSubscriber implements EventSubscriberInterface
{
  private ConfigService $configs;

  public function __construct(ConfigService $configs)
  {
    $this->configs = $configs;
  }

  public static function getSubscribedEvents(): array
  {
    return [
      'sales_channel.' . ProductEvents::PRODUCT_LOADED_EVENT => ['onProductsLoaded'],
    ];
  }

  public function onProductsLoaded(SalesChannelEntityLoadedEvent $event): void
  {
    $salesChannelId = $event->getSalesChannelContext()->getSalesChannelId();
    $mode = $this->configs->getConfig('environment', $salesChannelId);
    $minFinanceAmount = (float) $this->configs->getConfig('minFinanceAmount', $salesChannelId);
    $maxFinanceAmount = (float) $this->configs->getConfig('maxFinanceAmount', $salesChannelId);
    $storeCode = $this->configs->getConfig('storeCode', $salesChannelId);
    $dataMessage = $this->configs->getConfig('dataMessage', $salesChannelId);
    $showCredovaLogo = $this->configs->getConfig('showCredovaLogo', $salesChannelId);
    foreach ($event->getEntities() as $entity) {
      if (!$entity instanceof SalesChannelProductEntity) {
        continue;
      }

      $entity->addExtension('credovaFinance', new ArrayStruct([
        'minFinanceAmount' => $minFinanceAmount,
        'maxFinanceAmount' => $maxFinanceAmount,
        'storeCode' => $storeCode,
        'dataMessage' => $dataMessage,
        'showCredovaLogo' => $showCredovaLogo,
        'mode' => $mode,
      ]));
    }
  }
}