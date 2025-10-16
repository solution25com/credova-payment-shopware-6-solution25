<?php

namespace Credova\Service;

use Credova\Library\Constants\EnvironmentUrl;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class PaymentClientApi extends Endpoints
{
    private Client $client;
    private ConfigService $configs;
    private ?string $jwtToken = null;


    public function __construct(ConfigService $configs)
    {
        $this->configs = $configs;
    }

    public function requestAuthToken(string $salesChannelId = ''): ?string
    {
        $this->setupClient($salesChannelId);
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
            $this->jwtToken = $data['jwt'] ?? null;

            return $this->jwtToken;
        } catch (GuzzleException $e) {
            $this->jwtToken = null;
            return null;
        }
    }

    public function createApplication(array $body, string $salesChannelId = '', ?string $callbackUrl = null): array
    {
        $this->setupClient($salesChannelId);
        $endpoint = self::getEndpoint(self::CREATE_APPLICATIONS);

        $headers = [
        'Content-Type'  => 'application/json',
        'Authorization' => 'Bearer ' . $this->requestAuthToken(),
        ];

        if ($callbackUrl) {
            $headers['Callback-Url'] = $callbackUrl;
        }

        try {
            $response = $this->client->request($endpoint['method'], $endpoint['url'], [
            'headers' => $headers,
            'json'    => $body
            ]);
        } catch (GuzzleException $e) {
            return ['error' => $e->getMessage()];
        }

        return json_decode($response->getBody()->getContents(), true);
    }


    public function getStore()
    {
        $this->setupClient();
        $endpoint = self::getEndpoint(self::GET_STORE);

        $response = $this->client->request($endpoint['method'], $endpoint['url'], [
        'headers' => [
        'Content-Type' => 'application/x-www-form-urlencoded',
        'Authorization' => 'Bearer ' . $this->requestAuthToken(),
        ]
        ]);

        return $response->getBody()->getContents();
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

        $response = $this->client->request($endpoint['method'], $endpoint['url'], [
        'headers' => [
        'Content-Type'  => 'application/json',
        'Authorization' => 'Bearer ' . $this->requestAuthToken(),
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
        'timeout'  => 10.0,
        ]);
    }
}
