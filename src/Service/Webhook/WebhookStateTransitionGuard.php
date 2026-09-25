<?php

declare(strict_types=1);

namespace Credova\Service\Webhook;

class WebhookStateTransitionGuard
{
    private const ALLOWED_PAID = ['open', 'in_progress', 'credova_approved', 'credova_signed'];
    private const ALLOWED_FAIL_CANCEL = ['open', 'in_progress', 'credova_approved', 'credova_signed'];
    private const ALLOWED_APPROVED = ['open', 'in_progress'];

    public static function isTransitionAllowed(string $currentStateTechnicalName, string $action): bool
    {
        return match ($action) {
            'approved' => in_array($currentStateTechnicalName, self::ALLOWED_APPROVED, true),
            'signed', 'paid' => in_array($currentStateTechnicalName, self::ALLOWED_PAID, true),
            'fail', 'cancel' => in_array($currentStateTechnicalName, self::ALLOWED_FAIL_CANCEL, true),
            default => false,
        };
    }
}
