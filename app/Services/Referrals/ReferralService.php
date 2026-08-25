<?php

declare(strict_types=1);

namespace App\Services\Referrals;

use App\Models\Plan;
use App\Models\ReferralLedgerEntry;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Support\Subscriptions\BillingGrace;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Programa de referidos: bolsa de días + aplicación en la renovación.
 */
final class ReferralService
{
    public function resolveReferrer(?string $code): ?Tenant
    {
        $normalized = $this->normalizeCode($code);
        if ($normalized === null) {
            return null;
        }

        return Tenant::query()
            ->where(function ($q) use ($normalized): void {
                $q->whereRaw('UPPER(referral_code) = ?', [$normalized])
                    ->orWhereRaw('UPPER(slug) = ?', [$normalized]);
            })
            ->first();
    }

    public function ensureReferralCode(Tenant $tenant): string
    {
        if (is_string($tenant->referral_code) && $tenant->referral_code !== '') {
            return $tenant->referral_code;
        }

        $code = $this->uniqueCodeFromSlug((string) $tenant->slug);
        $tenant->forceFill(['referral_code' => $code])->save();

        return $code;
    }

    /**
     * Asigna el referidor al crear el tenant (provision / alta).
     */
    public function attachReferrer(Tenant $newTenant, ?string $referralCode): bool
    {
        $referrer = $this->resolveReferrer($referralCode);
        if ($referrer === null) {
            return false;
        }

        if ((string) $referrer->id === (string) $newTenant->id) {
            return false;
        }

        if ($newTenant->referido_por_tenant_id !== null) {
            return false;
        }

        $newTenant->forceFill([
            'referido_por_tenant_id' => $referrer->id,
            'canal_adquisicion' => 'referido',
        ])->save();

        return true;
    }

    /**
     * Tras un pago procesado del referido: acredita días al referidor (bolsa).
     */
    public function creditReferrerOnPaidPayment(Tenant $payerTenant, SubscriptionPayment $payment): void
    {
        if ($payerTenant->referido_por_tenant_id === null) {
            return;
        }

        if (! $this->isBillableProcessedPayment($payment)) {
            return;
        }

        $plan = $payment->plan_id
            ? Plan::query()->find($payment->plan_id)
            : null;
        if ($plan !== null && strtolower((string) $plan->codigo) === 'free') {
            return;
        }

        // Solo el primer pago billable del referido genera premio.
        $priorPaid = SubscriptionPayment::query()
            ->where('tenant_id', $payerTenant->id)
            ->where('estado', 'procesado')
            ->where('id', '!=', $payment->id)
            ->where(function ($q): void {
                $q->where('total', '>', 0)->orWhere('monto', '>', 0);
            })
            ->exists();

        if ($priorPaid) {
            return;
        }

        $already = ReferralLedgerEntry::query()
            ->where('referred_tenant_id', $payerTenant->id)
            ->where('type', ReferralLedgerEntry::TYPE_EARNED)
            ->exists();

        if ($already) {
            return;
        }

        $referrer = Tenant::query()->find($payerTenant->referido_por_tenant_id);
        if ($referrer === null) {
            return;
        }

        $days = $this->rewardDaysForPlan($plan);
        if ($days < 1) {
            Log::info('Referral reward skipped: plan has 0 referral days', [
                'plan' => $plan?->codigo,
                'referred' => $payerTenant->slug,
            ]);

            return;
        }

        $maxPerMonth = max(1, (int) config('referral.max_rewards_per_month', 10));
        $earnedThisMonth = ReferralLedgerEntry::query()
            ->where('referrer_tenant_id', $referrer->id)
            ->where('type', ReferralLedgerEntry::TYPE_EARNED)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        if ($earnedThisMonth >= $maxPerMonth) {
            Log::info('Referral reward skipped: monthly cap', [
                'referrer' => $referrer->slug,
                'referred' => $payerTenant->slug,
            ]);

            return;
        }

        $planLabel = $plan?->nombre ?? $plan?->codigo ?? 'plan';

        DB::transaction(function () use ($referrer, $payerTenant, $payment, $days, $planLabel): void {
            $locked = Tenant::query()->whereKey($referrer->id)->lockForUpdate()->first();
            if ($locked === null) {
                return;
            }

            $locked->forceFill([
                'referral_days_balance' => (int) $locked->referral_days_balance + $days,
            ])->save();

            ReferralLedgerEntry::query()->create([
                'referrer_tenant_id' => $locked->id,
                'referred_tenant_id' => $payerTenant->id,
                'subscription_payment_id' => $payment->id,
                'days' => $days,
                'type' => ReferralLedgerEntry::TYPE_EARNED,
                'notes' => 'Primer pago del referido '.$payerTenant->slug.' ('.$planLabel.')',
                'created_at' => now(),
            ]);
        });
    }

    public function rewardDaysForPlan(?Plan $plan): int
    {
        if ($plan === null || $plan->isFree()) {
            return 0;
        }

        $fromPlan = (int) ($plan->referral_reward_days ?? 0);
        if ($fromPlan > 0) {
            return $fromPlan;
        }

        // Fallback legacy si el plan aún no tiene valor (migraciones viejas).
        return max(0, (int) config('referral.reward_days', 0));
    }

    /**
     * En renovación: suma la bolsa al period_end y deja la bolsa en 0.
     *
     * @return array{period_end: CarbonInterface, days_applied: int}
     */
    public function applyBalanceToPeriodEnd(Tenant $tenant, CarbonInterface $periodEnd): array
    {
        $days = 0;
        $newEnd = $periodEnd->copy();

        DB::transaction(function () use ($tenant, &$periodEnd, &$days, &$newEnd): void {
            $locked = Tenant::query()->whereKey($tenant->id)->lockForUpdate()->first();
            if ($locked === null) {
                return;
            }

            $days = (int) $locked->referral_days_balance;
            if ($days < 1) {
                $newEnd = $periodEnd->copy();

                return;
            }

            $newEnd = $periodEnd->copy()->addDays($days);
            $locked->forceFill(['referral_days_balance' => 0])->save();

            ReferralLedgerEntry::query()->create([
                'referrer_tenant_id' => $locked->id,
                'referred_tenant_id' => null,
                'subscription_payment_id' => null,
                'days' => -$days,
                'type' => ReferralLedgerEntry::TYPE_APPLIED,
                'notes' => 'Aplicados en renovación → '.$newEnd->toDateString(),
                'created_at' => now(),
            ]);
        });

        return [
            'period_end' => $newEnd,
            'days_applied' => $days,
        ];
    }

    /**
     * Soporte: suma días a la bolsa (o aplica ya al periodo activo).
     */
    public function grantDaysManual(
        Tenant $tenant,
        int $days,
        bool $applyNow = false,
        ?string $notes = null,
    ): void {
        if ($days === 0) {
            throw new InvalidArgumentException('Los días no pueden ser 0.');
        }

        DB::transaction(function () use ($tenant, $days, $applyNow, $notes): void {
            $locked = Tenant::query()->whereKey($tenant->id)->lockForUpdate()->firstOrFail();

            if ($applyNow && $days > 0) {
                $sub = $locked->activeSubscription();
                if ($sub === null) {
                    throw new InvalidArgumentException('El tenant no tiene suscripción activa para aplicar días.');
                }
                $this->extendSubscriptionPeriod($sub, $days);
                ReferralLedgerEntry::query()->create([
                    'referrer_tenant_id' => $locked->id,
                    'referred_tenant_id' => null,
                    'subscription_payment_id' => null,
                    'days' => $days,
                    'type' => ReferralLedgerEntry::TYPE_MANUAL_GRANT,
                    'notes' => $notes ?? 'Aplicación manual inmediata',
                    'created_at' => now(),
                ]);

                return;
            }

            $newBalance = max(0, (int) $locked->referral_days_balance + $days);
            $locked->forceFill(['referral_days_balance' => $newBalance])->save();

            ReferralLedgerEntry::query()->create([
                'referrer_tenant_id' => $locked->id,
                'referred_tenant_id' => null,
                'subscription_payment_id' => null,
                'days' => $days,
                'type' => $days > 0
                    ? ReferralLedgerEntry::TYPE_MANUAL_GRANT
                    : ReferralLedgerEntry::TYPE_MANUAL_ADJUST,
                'notes' => $notes ?? 'Ajuste manual de bolsa',
                'created_at' => now(),
            ]);
        });
    }

    public function extendSubscriptionPeriod(Subscription $subscription, int $days): void
    {
        $baseEnd = $subscription->proximo_cobro_at
            ?? $subscription->current_period_end
            ?? now();

        if ($baseEnd instanceof CarbonInterface && $baseEnd->isPast()) {
            $baseEnd = now();
        }

        $newEnd = Carbon::parse($baseEnd)->addDays($days);

        $subscription->update([
            'current_period_end' => $newEnd,
            'proximo_cobro_at' => $newEnd,
            'grace_ends_at' => BillingGrace::endsAtFrom($newEnd),
        ]);
    }

    public function shareUrl(Tenant $tenant): string
    {
        $code = $this->ensureReferralCode($tenant);
        $template = (string) config('referral.share_url_template', 'https://orvae.pe/software/VETSAAS?ref={code}');

        return str_replace('{code}', urlencode($code), $template);
    }

    /**
     * @return array{
     *     referral_code: string,
     *     share_url: string,
     *     reward_days: int,
     *     days_balance: int,
     *     referred: list<array<string, mixed>>,
     *     ledger: list<array<string, mixed>>
     * }
     */
    public function summaryForTenant(Tenant $tenant): array
    {
        $code = $this->ensureReferralCode($tenant);

        $referred = Tenant::query()
            ->where('referido_por_tenant_id', $tenant->id)
            ->orderByDesc('created_at')
            ->get(['id', 'slug', 'nombre_comercial', 'razon_social', 'estado', 'created_at']);

        $earnedIds = ReferralLedgerEntry::query()
            ->where('referrer_tenant_id', $tenant->id)
            ->where('type', ReferralLedgerEntry::TYPE_EARNED)
            ->pluck('referred_tenant_id')
            ->filter()
            ->all();

        $referredRows = $referred->map(function (Tenant $t) use ($earnedIds): array {
            $rewarded = in_array($t->id, $earnedIds, true);

            return [
                'id' => $t->id,
                'slug' => $t->slug,
                'name' => $t->nombre_comercial ?: $t->razon_social,
                'estado' => $t->estado,
                'created_at' => $t->created_at?->toIso8601String(),
                'reward_status' => $rewarded ? 'credited' : 'pending_payment',
            ];
        })->values()->all();

        $ledger = ReferralLedgerEntry::query()
            ->where('referrer_tenant_id', $tenant->id)
            ->orderByDesc('created_at')
            ->limit(30)
            ->get()
            ->map(fn (ReferralLedgerEntry $e): array => [
                'id' => $e->id,
                'days' => $e->days,
                'type' => $e->type,
                'notes' => $e->notes,
                'created_at' => $e->created_at?->toIso8601String(),
            ])
            ->all();

        return [
            'referral_code' => $code,
            'share_url' => $this->shareUrl($tenant),
            'reward_days' => null,
            'rewards_by_plan' => $this->rewardsByPlanCatalog(),
            'days_balance' => (int) $tenant->fresh()?->referral_days_balance,
            'referred' => $referredRows,
            'ledger' => $ledger,
        ];
    }

    /**
     * @return list<array{codigo: string, nombre: string, days: int, label: string}>
     */
    public function rewardsByPlanCatalog(): array
    {
        return Plan::query()
            ->where('activo', true)
            ->where('es_publico', true)
            ->where('codigo', '!=', Plan::CODIGO_FREE)
            ->orderBy('orden')
            ->get(['codigo', 'nombre', 'referral_reward_days'])
            ->map(function (Plan $plan): array {
                $days = (int) $plan->referral_reward_days;

                return [
                    'codigo' => (string) $plan->codigo,
                    'nombre' => (string) $plan->nombre,
                    'days' => $days,
                    'label' => $days >= 28
                        ? '1 mes gratis'
                        : ($days > 0 ? $days.' días' : 'Sin premio'),
                ];
            })
            ->values()
            ->all();
    }

    private function isBillableProcessedPayment(SubscriptionPayment $payment): bool
    {
        if (($payment->estado ?? '') !== 'procesado') {
            return false;
        }

        $total = (float) ($payment->total ?? $payment->monto ?? 0);

        return $total > 0;
    }

    private function normalizeCode(?string $code): ?string
    {
        if ($code === null) {
            return null;
        }

        $code = strtoupper(trim($code));
        $code = preg_replace('/[^A-Z0-9\-]/', '', $code) ?? '';

        return $code !== '' ? $code : null;
    }

    private function uniqueCodeFromSlug(string $slug): string
    {
        $base = strtoupper(preg_replace('/[^A-Z0-9\-]/', '', strtoupper($slug)) ?: 'REF');
        $base = mb_substr($base, 0, 36);
        $candidate = $base;
        $n = 1;

        while (Tenant::query()->where('referral_code', $candidate)->exists()) {
            $candidate = mb_substr($base, 0, 32).'-'.$n;
            $n++;
        }

        return $candidate;
    }
}
