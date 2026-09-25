<?php

declare(strict_types=1);

namespace Credova\Tests\Unit;

use Credova\Service\Webhook\WebhookStateTransitionGuard;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Credova\Service\Webhook\WebhookStateTransitionGuard
 */
class WebhookStateTransitionGuardTest extends TestCase
{
    /**
     * @dataProvider transitionProvider
     */
    public function testTransition(string $currentState, string $action, bool $expected): void
    {
        static::assertSame($expected, WebhookStateTransitionGuard::isTransitionAllowed($currentState, $action));
    }

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function transitionProvider(): iterable
    {
        yield 'approve an open transaction' => ['open', 'approved', true];
        yield 'approve an in-progress transaction' => ['in_progress', 'approved', true];
        yield 'approve an already approved transaction' => ['credova_approved', 'approved', false];
        yield 'approve a paid transaction' => ['paid', 'approved', false];
        yield 'sign an approved transaction' => ['credova_approved', 'signed', true];
        yield 'sign a paid transaction' => ['paid', 'signed', false];
        yield 'fund a signed transaction' => ['credova_signed', 'paid', true];
        yield 'fund a paid transaction' => ['paid', 'paid', false];
        yield 'decline a signed transaction' => ['credova_signed', 'fail', true];
        yield 'decline a refunded transaction' => ['refunded', 'fail', false];
        yield 'return an approved transaction' => ['credova_approved', 'cancel', true];
        yield 'return a cancelled transaction' => ['cancelled', 'cancel', false];
        yield 'unknown action' => ['open', 'something', false];
    }
}
