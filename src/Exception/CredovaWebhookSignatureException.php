<?php

declare(strict_types=1);

namespace Credova\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class CredovaWebhookSignatureException extends ShopwareHttpException
{
    public function __construct()
    {
        parent::__construct('Invalid Credova webhook signature');
    }

    public function getErrorCode(): string
    {
        return 'CREDOVA__WEBHOOK_SIGNATURE_INVALID';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_UNAUTHORIZED;
    }
}
