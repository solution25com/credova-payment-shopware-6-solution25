<?php

namespace Credova\Service;

abstract class Endpoints
{
  protected const AUTH_TOKEN = 'AUTH_TOKEN';
  protected const GET_STORE = 'GET_STORE';
  protected const CREATE_APPLICATIONS = 'CREATE_APPLICATIONS';
  protected const WEBHOOK = 'credova/webhook';
  protected const RETURN_APPLICATIONS = 'RETURN_APPLICATIONS';

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

  public static function buildReturnApplicationUrl(string $publicId): array
  {
    $endpoint = self::getEndpoint(self::RETURN_APPLICATIONS);

    return [
      'method' => $endpoint['method'],
      'url' => str_replace('{publicId}', $publicId, $endpoint['url']),
    ];
  }
}
