<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Subscriptions;

use App\Models\Subscription;
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

    public function test_active_con_cobro_vencido_y_gracia_futura_esta_en_periodo_de_gracia(): void
    {
        $subscription = new Subscription([
            'estado' => 'active',
            'proximo_cobro_at' => Carbon::parse('2026-06-22 15:44:00'),
            'grace_ends_at' => Carbon::parse('2026-06-25 15:44:00'),
        ]);

        $this->assertTrue(SubscriptionExpiry::isInGracePeriod($subscription));
        $this->assertSame('grace_ends_at', SubscriptionExpiry::anchorWithSource($subscription)[1]);
        $this->assertSame(0, SubscriptionExpiry::daysUntil(SubscriptionExpiry::anchor($subscription)));
        $this->assertSame('amber', SubscriptionExpiry::urgencyFor($subscription));
    }

    public function test_active_antes_del_cobro_no_esta_en_gracia_aunque_tenga_grace_ends_at(): void
    {
        $subscription = new Subscription([
            'estado' => 'active',
            'proximo_cobro_at' => Carbon::parse('2026-06-28 12:00:00'),
            'grace_ends_at' => Carbon::parse('2026-07-01 12:00:00'),
        ]);

        $this->assertFalse(SubscriptionExpiry::isInGracePeriod($subscription));
        $this->assertSame('proximo_cobro_at', SubscriptionExpiry::anchorWithSource($subscription)[1]);
    }
}
