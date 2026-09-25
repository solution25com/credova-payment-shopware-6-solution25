<?php

declare(strict_types=1);

namespace Credova\Tests\Unit;

use Credova\Credova;
use Credova\Gateways\CredovaHandler;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;
use Symfony\Component\DependencyInjection\Container;

/**
 * @covers \Credova\Credova
 */
class CredovaLifecycleTest extends TestCase
{
    private const PAYMENT_METHOD_ID = '0190a1b2c3d4e5f60718293a4b5c6d7e';
    private const PLUGIN_ID = '0190a1b2c3d4e5f60718293a4b5c6d7f';

    private Context $context;

    /** @var EntityRepository&MockObject */
    private EntityRepository $paymentRepository;

    private Credova $plugin;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->paymentRepository = $this->createMock(EntityRepository::class);

        $pluginIdProvider = $this->createMock(PluginIdProvider::class);
        $pluginIdProvider->method('getPluginIdByBaseClass')->willReturn(self::PLUGIN_ID);

        $container = new Container();
        $container->set('payment_method.repository', $this->paymentRepository);
        $container->set(PluginIdProvider::class, $pluginIdProvider);

        $this->plugin = new Credova(true, __DIR__);
        $this->plugin->setContainer($container);
    }

    public function testLookupFiltersOnTheHandlerIdentifierThatIsPersisted(): void
    {
        $this->paymentRepository->expects(static::once())
            ->method('searchIds')
            ->with(static::callback(function (Criteria $criteria): bool {
                $filters = $criteria->getFilters();
                static::assertCount(1, $filters);
                static::assertInstanceOf(EqualsFilter::class, $filters[0]);
                static::assertSame('handlerIdentifier', $filters[0]->getField());
                static::assertSame(CredovaHandler::class, $filters[0]->getValue());

                return true;
            }))
            ->willReturn($this->idResult([]));

        $this->paymentRepository->method('create');

        $this->plugin->install($this->installContext(InstallContext::class));
    }

    public function testInstallCreatesThePaymentMethodWithAStableTechnicalName(): void
    {
        $this->paymentRepository->method('searchIds')->willReturn($this->idResult([]));

        $this->paymentRepository->expects(static::once())
            ->method('create')
            ->with(static::callback(function (array $payload): bool {
                static::assertSame(CredovaHandler::class, $payload[0]['handlerIdentifier']);
                static::assertSame('credova_payment', $payload[0]['technicalName']);
                static::assertSame(self::PLUGIN_ID, $payload[0]['pluginId']);

                return true;
            }));

        $this->plugin->install($this->installContext(InstallContext::class));
    }

    public function testReinstallDoesNotCreateASecondPaymentMethod(): void
    {
        $this->paymentRepository->method('searchIds')->willReturn($this->idResult([self::PAYMENT_METHOD_ID]));
        $this->paymentRepository->expects(static::never())->method('create');

        $this->plugin->install($this->installContext(InstallContext::class));
    }

    public function testActivateEnablesTheExistingPaymentMethod(): void
    {
        $this->paymentRepository->method('searchIds')->willReturn($this->idResult([self::PAYMENT_METHOD_ID]));
        $this->paymentRepository->expects(static::once())
            ->method('update')
            ->with([['id' => self::PAYMENT_METHOD_ID, 'active' => true]], $this->context);

        $this->plugin->activate($this->installContext(ActivateContext::class));
    }

    public function testDeactivateDisablesTheExistingPaymentMethod(): void
    {
        $this->paymentRepository->method('searchIds')->willReturn($this->idResult([self::PAYMENT_METHOD_ID]));
        $this->paymentRepository->expects(static::once())
            ->method('update')
            ->with([['id' => self::PAYMENT_METHOD_ID, 'active' => false]], $this->context);

        $this->plugin->deactivate($this->installContext(DeactivateContext::class));
    }

    public function testUninstallDisablesTheExistingPaymentMethod(): void
    {
        $this->paymentRepository->method('searchIds')->willReturn($this->idResult([self::PAYMENT_METHOD_ID]));
        $this->paymentRepository->expects(static::once())
            ->method('update')
            ->with([['id' => self::PAYMENT_METHOD_ID, 'active' => false]], $this->context);

        $this->plugin->uninstall($this->installContext(UninstallContext::class));
    }

    /**
     * @template T of InstallContext
     *
     * @param class-string<T> $class
     *
     * @return T&MockObject
     */
    private function installContext(string $class): InstallContext
    {
        $installContext = $this->createMock($class);
        $installContext->method('getContext')->willReturn($this->context);

        return $installContext;
    }

    /**
     * @param list<string> $ids
     */
    private function idResult(array $ids): IdSearchResult
    {
        $rows = array_map(static fn (string $id): array => ['primaryKey' => $id, 'data' => []], $ids);

        return new IdSearchResult(\count($rows), $rows, new Criteria(), $this->context);
    }
}
