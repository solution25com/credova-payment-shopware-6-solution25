<?php

namespace Credova\Subscriber;

use Credova\Service\ConfigService;
use Credova\Storefront\Struct\CheckoutTemplateCustomData;
use Shopware\Storefront\Page\Product\ProductPageLoadedEvent;
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
      ProductPageLoadedEvent::class => 'addPaymentMethodSpecificFormFields',
      'sales_channel.' . ProductEvents::PRODUCT_LOADED_EVENT => ['onProductsLoaded'],
    ];
  }

  public function addPaymentMethodSpecificFormFields(ProductPageLoadedEvent $event): void{
    $minFinanceAmount = $this->configs->getConfig('minFinanceAmount', $event->getSalesChannelContext()->getSalesChannelId());
    $maxFinanceAmount = $this->configs->getConfig('maxFinanceAmount', $event->getSalesChannelContext()->getSalesChannelId());
    $pageObject = $event->getPage();
    $templateVariables = new CheckoutTemplateCustomData();

    $templateVariables->assign([
      'template' => '@Storefront/credova-pages/credova-product-page.html.twig',
      'page' => $pageObject,
      'token' => 'pass here',
      'publicId'  => 'pass here',
      'minFinanceAmount'=> $minFinanceAmount,
      'maxFinanceAmount'=> $maxFinanceAmount      
    ]);

    $pageObject->addExtension(
      CheckoutTemplateCustomData::EXTENSION_NAME,
      $templateVariables
    );
  }

  public function onProductsLoaded(SalesChannelEntityLoadedEvent $event): void
  {
      $salesChannelId = $event->getSalesChannelContext()->getSalesChannelId();
      $minFinanceAmount = (float) $this->configs->getConfig('minFinanceAmount', $salesChannelId);
      $maxFinanceAmount = (float) $this->configs->getConfig('maxFinanceAmount', $salesChannelId);

      foreach ($event->getEntities() as $entity) {
          if (!$entity instanceof SalesChannelProductEntity) {
              continue;
          }
  
          $entity->addExtension('credovaFinance', new ArrayStruct([
              'minFinanceAmount' => $minFinanceAmount,
              'maxFinanceAmount' => $maxFinanceAmount,
          ]));
      }
  }
}