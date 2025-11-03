<?php

declare(strict_types=1);

namespace Credova\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class CredovaValidationException extends ShopwareHttpException
{
    public function __construct(string $message, array $parameters = [])
    {
        parent::__construct($message, $parameters);
    }

    public function getErrorCode(): string
    {
        return 'CREDOVA__VALIDATION_ERROR';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
