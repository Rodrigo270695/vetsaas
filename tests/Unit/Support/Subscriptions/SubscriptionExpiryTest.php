<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Subscriptions;

use App\Support\Subscriptions\SubscriptionExpiry;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SubscriptionExpiryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-25 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[DataProvider('urgencyProvider')]
    public function test_urgency_levels(string $estado, ?int $daysUntil, string $expected): void
    {
        $this->assertSame($expected, SubscriptionExpiry::urgency($estado, $daysUntil));
    }

    /**
     * @return array<string, array{0: string, 1: int|null, 2: string}>
     */
    public static function urgencyProvider(): array
    {
        return [
            'ok' => ['active', 10, 'ok'],
            'yellow_7' => ['active', 7, 'yellow'],
            'amber_3' => ['active', 3, 'amber'],
            'red_1' => ['active', 1, 'red'],
            'red_today' => ['active', 0, 'red'],
            'red_expired' => ['active', -2, 'red'],
            'danger_suspended' => ['suspended', 5, 'danger'],
            'muted_no_days' => ['active', null, 'muted'],
        ];
    }

    public function test_days_until_from_anchor(): void
    {
        $anchor = Carbon::parse('2026-06-28');

        $this->assertSame(3, SubscriptionExpiry::daysUntil($anchor));
    }

    public function test_due_at_sql_is_non_empty(): void
    {
        $sql = SubscriptionExpiry::dueAtSql();

        $this->assertStringContainsString('grace_ends_at', $sql);
        $this->assertStringContainsString('trial_ends_at', $sql);
        $this->assertStringContainsString('proximo_cobro_at', $sql);
    }
}
