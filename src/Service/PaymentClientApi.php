<?php

namespace Credova\Service;

use Credova\Library\Constants\EnvironmentUrl;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Credova\Exception\CredovaAuthException;
use Credova\Exception\CredovaApiException;
use Random\RandomException;

class PaymentClientApi extends Endpoints
{
    private ?Client $client = null;
    private ConfigService $configs;
    private ?string $currentBaseUrl = null;
    /** @var array<string,array{token:string,expiresAt:int}> */
    private array $tokenCache = [];


    public function __construct(ConfigService $configs)
    {
        $this->configs = $configs;
    }

    public function requestAuthToken(string $salesChannelId = ''): string
    {
        $this->setupClient($salesChannelId);

        if (isset($this->tokenCache[$salesChannelId])) {
            $cached = $this->tokenCache[$salesChannelId];
            if ($cached['expiresAt'] > time() + 5) {
                return $cached['token'];
            }
        }

        $endpoint = self::getEndpoint(self::AUTH_TOKEN);
        $username = $this->configs->getConfig('username', $salesChannelId);
        $password = $this->configs->getConfig('password', $salesChannelId);

        try {
            $response = $this->client->request($endpoint['method'], $endpoint['url'], [
            'headers' => [
            'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'form_params' => [
            'username' => $username,
            'password' => $password,
            ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $token = $data['jwt'] ?? '';
            if (!is_string($token) || $token === '') {
                throw new CredovaAuthException('Failed to obtain JWT from Credova');
            }
            $expiresAt = $this->checkExpireTimestamp($token) ?? (time() + 55 * 60);
            $this->tokenCache[$salesChannelId] = ['token' => $token, 'expiresAt' => $expiresAt];
            return $token;
        } catch (GuzzleException $e) {
            throw new CredovaAuthException('Credova auth request failed', ['reason' => $e->getMessage()]);
        }
    }


    public function createApplication(array $body, string $salesChannelId = '', ?string $callbackUrl = null): array
    {
        $this->setupClient($salesChannelId);
        $endpoint = self::getEndpoint(self::CREATE_APPLICATIONS);

        $contents = $this->requestWithAuth($salesChannelId, function (string $token, string $requestId) use ($endpoint, $body, $callbackUrl) {
            $headers = [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $token,
            'X-Request-Id'  => $requestId,
            ];

            if ($callbackUrl) {
                $headers['Callback-Url'] = $callbackUrl;
            }

            try {
                $response = $this->client->request($endpoint['method'], $endpoint['url'], [
                'headers' => $headers,
                'json'    => $body
                ]);
                return $response->getBody()->getContents();
            } catch (GuzzleException $e) {
                throw new CredovaApiException('Credova create application failed', ['reason' => $e->getMessage(), 'requestId' => $requestId]);
            }
        });

        return json_decode($contents, true);
    }


    public function getStore(string $salesChannelId = '')
    {
        $this->setupClient($salesChannelId);
        $endpoint = self::getEndpoint(self::GET_STORE);

        return $this->requestWithAuth($salesChannelId, function (string $token, string $requestId) use ($endpoint) {
            try {
                $response = $this->client->request($endpoint['method'], $endpoint['url'], [
                'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                  'Authorization' => 'Bearer ' . $token,
                  'X-Request-Id' => $requestId,
                ]
                ]);
                return $response->getBody()->getContents();
            } catch (GuzzleException $e) {
                throw new CredovaApiException('Credova get store failed', ['reason' => $e->getMessage(), 'requestId' => $requestId]);
            }
        });
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

    private function sendApplicationRequest(string $publicId, string $endpointKey, array $data = []): string
    {
        $this->setupClient();
        $endpoint = Endpoints::buildApplicationUrl($publicId, Endpoints::getEndpoint($endpointKey));

        return $this->requestWithAuth('', function (string $token, string $requestId) use ($endpoint, $data) {
            try {
                $response = $this->client->request($endpoint['method'], $endpoint['url'], [
                'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $token,
                'X-Request-Id' => $requestId,
                ],
                'json' => $data,
                ]);
                return $response->getBody()->getContents();
            } catch (GuzzleException $e) {
                throw new CredovaApiException('Credova application request failed', ['reason' => $e->getMessage(), 'requestId' => $requestId]);
            }
        });
    }

    public function testConnection(?string $salesChannelId = null): bool
    {
        try {
            $this->requestAuthToken($salesChannelId);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }


  /**
   * @throws RandomException
   */
    private function requestWithAuth(string $salesChannelId, callable $doRequest): string
    {
        $requestId = bin2hex(random_bytes(8));
        $token = $this->requestAuthToken($salesChannelId);

        try {
            return $doRequest($token, $requestId);
        } catch (CredovaApiException $e) {
            unset($this->tokenCache[$salesChannelId]);
            $token = $this->requestAuthToken($salesChannelId);
            return $doRequest($token, $requestId);
        }
    }

    private function checkExpireTimestamp(string $jwt): ?int
    {
        $parts = explode('.', $jwt);
        if (count($parts) < 2) {
            return null;
        }
        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
        if (!is_array($payload) || !isset($payload['exp'])) {
            return null;
        }
        $exp = (int) $payload['exp'];
        return $exp > 0 ? $exp : null;
    }


    private function setupClient(string $salesChannelId = ''): void
    {
        $mode = $this->configs->getConfig('environment', $salesChannelId);
        $isProd = $mode === 'production';
        $baseUrl = $isProd ? EnvironmentUrl::PROD : EnvironmentUrl::SANDBOX;

        if ($this->client === null || $this->currentBaseUrl !== $baseUrl) {
            $this->client = new Client([
            'base_uri' => $baseUrl,
            'timeout'  => 10.0,
            ]);

            $this->currentBaseUrl = $baseUrl;
        }
    }
}
