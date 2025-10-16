<?php

namespace Credova\Service;

use Shopware\Core\System\SystemConfig\SystemConfigService;

class ConfigService
{
    public function __construct(private readonly SystemConfigService $systemConfigService)
    {
    }

    public function getConfig(string $configName, ?string $salesChannelId = null): mixed
    {
        return $this->systemConfigService->get('Credova.config.' . trim($configName), $salesChannelId);
    }
}
