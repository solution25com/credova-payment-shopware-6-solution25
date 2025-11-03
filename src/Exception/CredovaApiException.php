<?php

declare(strict_types=1);

namespace Credova\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class CredovaApiException extends ShopwareHttpException
{
    public function __construct(string $message = 'Credova API error', array $parameters = [])
    {
        parent::__construct($message, $parameters);
    }

    public function getErrorCode(): string
    {
        return 'CREDOVA__API_ERROR';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_GATEWAY;
    }
}
