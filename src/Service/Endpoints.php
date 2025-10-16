<?php

namespace Credova\Service;

abstract class Endpoints
{
    protected const AUTH_TOKEN = 'AUTH_TOKEN';
    protected const GET_STORE = 'GET_STORE';
    protected const CREATE_APPLICATIONS = 'CREATE_APPLICATIONS';
    protected const WEBHOOK = 'credova/webhook';
    protected const RETURN_APPLICATIONS = 'RETURN_APPLICATIONS';
    protected const DELIVERY_INFORMATION = 'DELIVERY_INFORMATION';
    protected const REFERENCES_TO_ORDER = 'REFERENCES_TO_ORDER';

    private static array $endpoints = [
    self::AUTH_TOKEN => [
      'method'  => 'POST',
      'url' => '/v2/token'
    ],
    self::GET_STORE => [
      'method' => 'GET',
      'url' => '/v2/Stores'
    ],
    self::CREATE_APPLICATIONS => [
      'method' => 'POST',
      'url' => '/v2/applications'
    ],
    self::RETURN_APPLICATIONS => [
      'method' => 'POST',
      'url' => '/v2/applications/{publicId}/requestreturn'
    ],
    self::DELIVERY_INFORMATION => [
      'method' => 'POST',
      'url' => '/v2/applications/{publicId}/deliveryinformation'
    ],
    self::REFERENCES_TO_ORDER => [
      'method' => 'POST',
      'url' => '/v2/applications/{publicId}/orders'
    ]
    ];

    protected static function getEndpoint(string $endpoint): array
    {
        return self::$endpoints[$endpoint];
    }

    public static function callbackUrl(string $domain): string
    {
        return rtrim($domain, '/') . '/' . self::WEBHOOK;
    }

    public static function buildApplicationUrl(string $publicId, $endpoint): array
    {
        return [
        'method' => $endpoint['method'],
        'url' => str_replace('{publicId}', $publicId, $endpoint['url']),
        ];
    }
}
