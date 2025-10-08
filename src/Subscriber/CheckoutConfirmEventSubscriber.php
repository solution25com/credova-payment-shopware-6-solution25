<?php declare(strict_types=1);

namespace Credova\Subscriber;

use Credova\Service\ConfigService;
use Credova\Gateways\CredovaHandler;
use Credova\Service\CustomerDataValidator;
use Credova\Service\PaymentClientApi;
use Credova\Storefront\Struct\CheckoutTemplateCustomData;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CheckoutConfirmEventSubscriber implements EventSubscriberInterface
{
  private CustomerDataValidator $customerDataValidator;
  private ConfigService $configs;

  public function __construct(CustomerDataValidator $customerDataValidator, ConfigService $configs)
  {
    $this->customerDataValidator = $customerDataValidator;
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
    $selectedPaymentGateway = $event->getSalesChannelContext()->getPaymentMethod();
    if ($selectedPaymentGateway->getHandlerIdentifier() !== CredovaHandler::class) {
      return;
    }

    $salesChannelId = $event->getSalesChannelContext()->getSalesChannelId();
    $context = $event->getSalesChannelContext()->getContext();
    $pageObject = $event->getPage();
    $cartAmount = $pageObject->getCart()->getPrice()->getTotalPrice();

    $customerId = $event->getSalesChannelContext()->getCustomer()?->getId();
    $errors = [];

    if (!$this->customerDataValidator->validateCredovaPayment($cartAmount, $salesChannelId)) {
      $errors['Cart Amount'] = 'Credova is not possible to proceed on this cart amount.';
    } else {
      $errors = $this->customerDataValidator->validate($customerId, $context);
    }

    $mode = $this->configs->getConfig('environment', $salesChannelId);
    $storeCode = $this->configs->getConfig('storeCode', $salesChannelId);

    $templateVariables = new CheckoutTemplateCustomData();
    $templateVariables->assign([
      'template' => '@Storefront/credova-pages/credova-pay-later.html.twig',
      'gateway' => 'CredovaHandler',
      'mode' => $mode,
      'storeCode' => $storeCode,
    ]);

    if (!empty($errors)) {
      $templateVariables->assign([
        'errors' => $errors
      ]);
    }

    $pageObject->addExtension(
      CheckoutTemplateCustomData::EXTENSION_NAME,
      $templateVariables
    );
  }
}
