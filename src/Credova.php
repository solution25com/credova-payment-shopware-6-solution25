<?php

declare(strict_types=1);

namespace Credova;

use Credova\PaymentMethods\CredovaPaymentMethod;
use Credova\PaymentMethods\PaymentMethods;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;

class Credova extends Plugin
{
    public function install(InstallContext $context): void
    {
        $this->addPaymentMethod($context->getContext());
    }

    public function uninstall(UninstallContext $context): void
    {
        $this->setPaymentMethodIsActive(false, $context->getContext());
    }

    public function activate(ActivateContext $context): void
    {
        $this->setPaymentMethodIsActive(true, $context->getContext());
        parent::activate($context);
    }


    public function deactivate(DeactivateContext $context): void
    {
        $this->setPaymentMethodIsActive(false, $context->getContext());
        parent::deactivate($context);
    }

    private function addPaymentMethod(Context $context): void
    {
        $paymentMethodExists = $this->getPaymentMethodId($context);

        if ($paymentMethodExists) {
            return;
        }

        $pluginIdProvider = $this->container->get(PluginIdProvider::class);
        $pluginId = $pluginIdProvider->getPluginIdByBaseClass(get_class($this), $context);

        $paymentMethod = new CredovaPaymentMethod();

        $credovaPaymentData = [
        'handlerIdentifier' => $paymentMethod->getHandlerIdentifier(),
        'name' => $paymentMethod->getName(),
        'description' => $paymentMethod->getDescription(),
        'pluginId' => $pluginId,
        'afterOrderEnabled' => true,
        'technicalName' => $paymentMethod->getTechnicalName(),
        ];

        $paymentRepository = $this->container->get('payment_method.repository');
        $paymentRepository->create([$credovaPaymentData], $context);
    }


    private function setPaymentMethodIsActive(bool $active, Context $context): void
    {
        $paymentRepository = $this->container->get('payment_method.repository');

        $paymentMethodId = $this->getPaymentMethodId($context);

        if (!$paymentMethodId) {
            return;
        }

        $paymentMethod = [
        'id' => $paymentMethodId,
        'active' => $active,
        ];

        $paymentRepository->update([$paymentMethod], $context);
    }

    private function getPaymentMethodId(Context $context): ?string
    {
        $paymentRepository = $this->container->get('payment_method.repository');

        $paymentCriteria = (new Criteria())->addFilter(
            new EqualsAnyFilter('handlerIdentifier', PaymentMethods::PAYMENT_METHODS)
        );

        return $paymentRepository->searchIds($paymentCriteria, $context)->firstId();
    }

    public function update(UpdateContext $updateContext): void
    {
    }

    public function postInstall(InstallContext $installContext): void
    {
    }

    public function postUpdate(UpdateContext $updateContext): void
    {
    }
}
