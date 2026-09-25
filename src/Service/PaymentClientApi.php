<?php

declare(strict_types=1);

namespace Credova\Service;

use Credova\Library\Constants\EnvironmentUrl;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class PaymentClientApi extends Endpoints
{
    private const CACHE_KEY_PREFIX = 'credova_jwt_';
    private const CACHE_TTL_SECONDS = 3500;

    private Client $client;

    public function __construct(
        private readonly ConfigService $configs,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger
    ) {
    }

    public function requestAuthToken(string $salesChannelId = ''): ?string
    {
        $this->setupClient($salesChannelId);
        $cacheKey = self::CACHE_KEY_PREFIX . md5($salesChannelId);
        try {
            return $this->cache->get($cacheKey, function (ItemInterface $item) use ($salesChannelId) {
                $item->expiresAfter(self::CACHE_TTL_SECONDS);
                $token = $this->fetchAuthTokenFromApi($salesChannelId);
                if ($token === null) {
                    throw new \RuntimeException('Credova auth returned no token');
                }
                return $token;
            });
        } catch (\Throwable $e) {
            $this->cache->delete($cacheKey);
            return $this->fetchAuthTokenFromApi($salesChannelId);
        }
    }

    private function fetchAuthTokenFromApi(string $salesChannelId): ?string
    {
        $endpoint = self::getEndpoint(self::AUTH_TOKEN);
        $username = $this->configs->getConfig('username', $salesChannelId);
        $password = $this->configs->getConfig('password', $salesChannelId);
        try {
            $response = $this->client->request($endpoint['method'], $endpoint['url'], [
                'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
                'form_params' => ['username' => $username, 'password' => $password],
            ]);
            $data = json_decode($response->getBody()->getContents(), true);
            $token = is_array($data) ? ($data['jwt'] ?? null) : null;
            return $token !== null && $token !== '' ? $token : null;
        } catch (GuzzleException $e) {
            $this->logger->error('Credova auth failed', ['message' => $e->getMessage()]);
            return null;
        }
    }

    public function createApplication(array $body, string $salesChannelId = '', ?string $callbackUrl = null): array
    {
        $this->setupClient($salesChannelId);
        $endpoint = self::getEndpoint(self::CREATE_APPLICATIONS);
        $token = $this->getTokenWithRetry($salesChannelId, 5);
        if ($token === null) {
            return ['error' => 'Authentication failed'];
        }

        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ];
        if ($callbackUrl !== null) {
            $headers['Callback-Url'] = $callbackUrl;
        }

        try {
            $response = $this->client->request($endpoint['method'], $endpoint['url'], [
                'headers' => $headers,
                'json' => $body,
            ]);
        } catch (GuzzleException $e) {
            return ['error' => $e->getMessage()];
        }

        $decoded = json_decode($response->getBody()->getContents(), true);
        return is_array($decoded) ? $decoded : [];
    }

    public function returnApplication(string $publicId, array $data = []): string
    {
        return $this->sendApplicationRequest($publicId, self::RETURN_APPLICATIONS, $data);
    }

    public function addDeliveryInformation(string $publicId, array $data = []): string
    {
        return $this->sendApplicationRequest($publicId, self::DELIVERY_INFORMATION, $data);
    }

    public function addReferencesToOrder(string $publicId, array $data = []): string
    {
        return $this->sendApplicationRequest($publicId, self::REFERENCES_TO_ORDER, $data);
    }

    private function getTokenWithRetry(string $salesChannelId, int $maxRetries): ?string
    {
        $token = $this->requestAuthToken($salesChannelId);
        if ($token !== null) {
            return $token;
        }
        if ($maxRetries <= 0) {
            return null;
        }
        $this->cache->delete(self::CACHE_KEY_PREFIX . md5($salesChannelId));
        return $this->requestAuthToken($salesChannelId);
    }

    private function sendApplicationRequest(string $publicId, string $endpointKey, array $data = []): string
    {
        $this->setupClient('');
        $endpoint = Endpoints::buildApplicationUrl($publicId, Endpoints::getEndpoint($endpointKey));
        $token = $this->requestAuthToken('');
        $response = $this->client->request($endpoint['method'], $endpoint['url'], [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ],
            'json' => $data,
        ]);
        return $response->getBody()->getContents();
    }

    private function setupClient(string $salesChannelId = ''): void
    {
        $mode = $this->configs->getConfig('environment', $salesChannelId);
        $isProd = $mode === 'production';
        $baseUrl = $isProd ? EnvironmentUrl::PROD : EnvironmentUrl::SANDBOX;
        $this->client = new Client([
            'base_uri' => $baseUrl,
            'timeout' => 10.0,
        ]);
    }
}
