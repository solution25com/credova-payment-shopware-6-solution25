<?php

namespace Credova\Service;

abstract class Endpoints
{
  protected const AUTH_TOKEN = 'AUTH_TOKEN';
  protected const GET_STORE = 'GET_STORE';
  protected const CREATE_APPLICATIONS = 'CREATE_APPLICATIONS';
  private const WEBHOOK = 'credova/webhook';


  private static array $endpoints = [
    self::AUTH_TOKEN => [
      'method'  => 'POST',
      'url' => '/v2/token'
    ],
    self::GET_STORE => [
      'method' => 'GET',
      'url' => 'v2/Stores'
    ],
    self::CREATE_APPLICATIONS => [
      'method' => 'POST',
      'url' => 'v2/applications'
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
}