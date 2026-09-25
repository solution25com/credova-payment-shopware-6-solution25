<?php

declare(strict_types=1);

namespace Credova\Subscriber;

use Credova\Service\ConfigService;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelEntityLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class ProductPageSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ConfigService $configs,
        private readonly RequestStack $requestStack
    ) {
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

        $request = $this->requestStack->getCurrentRequest();
        $isDetailPage = $request && str_contains($request->attributes->get('_route', ''), 'frontend.detail');

        $messageForContext = $isDetailPage ? '' : ($dataMessage ?? '');

        foreach ($event->getEntities() as $entity) {
            if (!$entity instanceof SalesChannelProductEntity) {
                continue;
            }

            $entity->addExtension('credovaFinance', new ArrayStruct([
            'minFinanceAmount' => $minFinanceAmount,
            'maxFinanceAmount' => $maxFinanceAmount,
            'storeCode' => $storeCode,
            'dataMessage' => $messageForContext,
            'showCredovaLogo' => $showCredovaLogo,
            'mode' => $mode,
            ]));
        }
    }
}
