<?php

declare(strict_types=1);

namespace Credova\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class CredovaAuthException extends ShopwareHttpException
{
    public function __construct(string $message = 'Credova authentication failed', array $parameters = [])
    {
        parent::__construct($message, $parameters);
    }

    public function getErrorCode(): string
    {
        return 'CREDOVA__AUTHENTICATION_FAILED';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_UNAUTHORIZED;
    }
}
