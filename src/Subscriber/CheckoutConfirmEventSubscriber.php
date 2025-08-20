<?php declare(strict_types=1);

namespace Credova\Subscriber;

use Credova\Service\ConfigService;
use Credova\Gateways\CredovaHandler;
use Credova\Service\PaymentClientApi;
use Credova\Storefront\Struct\CheckoutTemplateCustomData;
use NMIPayment\Gateways\CreditCard;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CheckoutConfirmEventSubscriber implements EventSubscriberInterface
{
  private PaymentClientApi $paymentClient;
  private ConfigService $configs;

  public function __construct(PaymentClientApi $paymentClient, ConfigService $configs)
  {
    $this->paymentClient = $paymentClient;
    $this->configs = $configs;
  }
  public static function getSubscribedEvents(): array
  {
    return [
      CheckoutConfirmPageLoadedEvent::class => 'addPaymentMethodSpecificFormFields',
    ];
  }

  public function addPaymentMethodSpecificFormFields(CheckoutConfirmPageLoadedEvent $event): void
  {
    $minFinanceAmount = $this->configs->getConfig('minFinanceAmount', $event->getSalesChannelContext()->getSalesChannelId());
    $maxFinanceAmount = $this->configs->getConfig('maxFinanceAmount', $event->getSalesChannelContext()->getSalesChannelId());
    $pageObject = $event->getPage();
    $cartAmount = $pageObject->getCart()->getPrice()->getTotalPrice();

    if ($cartAmount < $minFinanceAmount || $cartAmount > $maxFinanceAmount) {
      $paymentMethods = $pageObject->getPaymentMethods();
      $filteredPaymentMethods = $paymentMethods->filter(function (PaymentMethodEntity $paymentMethod) {
        return $paymentMethod->getHandlerIdentifier() !== CredovaHandler::class;
      });
      $pageObject->setPaymentMethods($filteredPaymentMethods);
      return;
    }

    $mode = $this->configs->getConfig('environment', $event->getSalesChannelContext()->getSalesChannelId());
    $storeDataJson = $this->paymentClient->getStore();
    $storeData = json_decode($storeDataJson, true);
    $publicId = $storeData[0]['publicId'] ?? null;
    $storeCode = $this->configs->getConfig('storeCode', $event->getSalesChannelContext()->getSalesChannelId());

    $salesChannelContext = $event->getSalesChannelContext();
    $selectedPaymentGateway = $salesChannelContext->getPaymentMethod();
    $templateVariables = new CheckoutTemplateCustomData();

    if($selectedPaymentGateway->getHandlerIdentifier() == CredovaHandler::class){

      $templateVariables->assign([
        'template' => '@Storefront/credova-pages/credova-pay-later.html.twig',
        'gateway' => 'CredovaHandler',
        'publicId' => $publicId,
        'mode' => $mode,
        'storeCode' => $storeCode
      ]);

      $pageObject->addExtension(
        CheckoutTemplateCustomData::EXTENSION_NAME,
        $templateVariables
      );
    }
  }
}
