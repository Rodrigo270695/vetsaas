<?php

declare(strict_types=1);

use App\Models\Subscription;
use App\Services\Subscriptions\SubscriptionPeriodCalculator;
use Illuminate\Support\Carbon;

function monthlySubscriptionEnding(string $periodEnd): Subscription
{
    $subscription = new Subscription;
    $subscription->ciclo = 'mensual';
    $subscription->current_period_start = Carbon::parse($periodEnd)->subMonth();
    $subscription->current_period_end = Carbon::parse($periodEnd);

    return $subscription;
}

it('mantiene el vencimiento del día uno cuando pagan antes', function (): void {
    $calculator = app(SubscriptionPeriodCalculator::class);
    $subscription = monthlySubscriptionEnding('2026-07-01 00:00:00');

    $start = $calculator->nextPeriodStart(
        $subscription,
        Carbon::parse('2026-06-29 15:00:00'),
    );

    expect($start->toDateString())->toBe('2026-07-01')
        ->and($calculator->nextPeriodEnd($start, 'mensual')->toDateString())
        ->toBe('2026-08-01');
});

it('mantiene el ciclo vigente cuando pagan después del vencimiento', function (): void {
    $calculator = app(SubscriptionPeriodCalculator::class);
    $subscription = monthlySubscriptionEnding('2026-07-01 00:00:00');

    $start = $calculator->nextPeriodStart(
        $subscription,
        Carbon::parse('2026-07-02 10:00:00'),
    );

    expect($start->toDateString())->toBe('2026-07-01')
        ->and($calculator->nextPeriodEnd($start, 'mensual')->toDateString())
        ->toBe('2026-08-01');
});

it('usa el mes que corre si dejaron pasar más de un ciclo', function (): void {
    $calculator = app(SubscriptionPeriodCalculator::class);
    $subscription = monthlySubscriptionEnding('2026-06-01 00:00:00');

    $start = $calculator->nextPeriodStart(
        $subscription,
        Carbon::parse('2026-07-10 10:00:00'),
    );

    expect($start->toDateString())->toBe('2026-07-01')
        ->and($calculator->nextPeriodEnd($start, 'mensual')->toDateString())
        ->toBe('2026-08-01');
});
