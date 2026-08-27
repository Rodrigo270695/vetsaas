<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\Subscriptions\FreeWinBackCredentialsNotification;
use App\Notifications\Subscriptions\FreeWinBackOfferNotification;
use App\Support\Subscriptions\SubscriptionExpiry;
use App\Support\Tenancy\TenantSubdomainUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

/**
 * Reenganche de planes Free vencidos por correo (sin celular).
 * Oferta → clic en enlace → trial +30 días + email con credenciales.
 */
final class FreePlanWinBackService
{
    public const OFFER_DAYS = 30;

    /** No reenviar oferta si hay pending más reciente que esto. */
    public const PENDING_COOLDOWN_HOURS = 72;

    /** No reenviar si aceptó hace menos de esto. */
    public const ACCEPTED_COOLDOWN_DAYS = 25;

    public function __construct(
        private readonly SubscriptionWinBackService $winBack,
    ) {}

    /**
     * @param  Builder<Subscription>  $query
     */
    public function scopeEligibleFreeExpired(Builder $query): Builder
    {
        return $query
            ->whereHas('plan', fn (Builder $q) => $q->where('codigo', Plan::CODIGO_FREE))
            ->whereNull('cancelled_at')
            ->where('estado', '!=', 'cancelled')
            ->whereIn('estado', ['trial', 'active', 'grace', 'suspended'])
            ->whereHas('tenant', function (Builder $q): void {
                $q->whereNotNull('email_admin')
                    ->where('email_admin', '!=', '')
                    ->where('estado', '!=', 'cancelled');
            });
    }

    /**
     * @param  list<string>  $tenantIds
     * @return Collection<int, Subscription>
     */
    public function findEligibleByTenantIds(array $tenantIds): Collection
    {
        $ids = array_values(array_filter(array_map('strval', $tenantIds)));
        if ($ids === []) {
            return collect();
        }

        return $this->scopeEligibleFreeExpired(Subscription::query())
            ->with(['tenant', 'plan'])
            ->whereIn('tenant_id', $ids)
            ->orderByDesc('updated_at')
            ->get()
            ->unique('tenant_id')
            ->filter(function (Subscription $subscription): bool {
                $tenant = $subscription->tenant;
                $days = SubscriptionExpiry::daysUntil(
                    SubscriptionExpiry::anchor($subscription, $tenant),
                );

                return $days !== null && $days < 0;
            })
            ->values();
    }

    /**
     * Todos los Free vencidos con email (tope de seguridad).
     *
     * @return Collection<int, Subscription>
     */
    public function findAllEligibleFreeExpired(int $limit = 500): Collection
    {
        return $this->scopeEligibleFreeExpired(Subscription::query())
            ->with(['tenant', 'plan'])
            ->orderByDesc('updated_at')
            ->limit(max(1, min($limit, 1000)))
            ->get()
            ->unique('tenant_id')
            ->filter(function (Subscription $subscription): bool {
                $tenant = $subscription->tenant;
                $days = SubscriptionExpiry::daysUntil(
                    SubscriptionExpiry::anchor($subscription, $tenant),
                );

                return $days !== null && $days < 0;
            })
            ->values();
    }

    /**
     * @return array{ok: bool, error: string|null, sent: bool, skipped: string|null}
     */
    public function sendOffer(Subscription $subscription, bool $force = false): array
    {
        $subscription->loadMissing(['tenant', 'plan']);

        if ($subscription->plan?->codigo !== Plan::CODIGO_FREE) {
            return ['ok' => false, 'error' => 'Solo aplica a plan Free.', 'sent' => false, 'skipped' => 'not_free'];
        }

        if ($subscription->estado === 'cancelled' || $subscription->cancelled_at !== null) {
            return ['ok' => false, 'error' => 'Suscripción cancelada.', 'sent' => false, 'skipped' => 'cancelled'];
        }

        $tenant = $subscription->tenant;
        if (! $tenant instanceof Tenant) {
            return ['ok' => false, 'error' => 'Tenant no encontrado.', 'sent' => false, 'skipped' => 'no_tenant'];
        }

        $email = strtolower(trim((string) $tenant->email_admin));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Sin correo admin válido.', 'sent' => false, 'skipped' => 'no_email'];
        }

        if (! $force) {
            $skip = $this->skipReason($subscription);
            if ($skip !== null) {
                return ['ok' => true, 'error' => null, 'sent' => false, 'skipped' => $skip];
            }

            $daysUntil = SubscriptionExpiry::daysUntil(
                SubscriptionExpiry::anchor($subscription, $tenant),
            );
            if ($daysUntil === null || $daysUntil >= 0) {
                return ['ok' => true, 'error' => null, 'sent' => false, 'skipped' => 'not_expired'];
            }
        }

        $token = Str::random(48);
        $acceptUrl = url('/win-back/free/'.$token);

        try {
            Notification::route('mail', $email)
                ->notify(new FreeWinBackOfferNotification(
                    tenant: $tenant,
                    acceptUrl: $acceptUrl,
                    offerDays: self::OFFER_DAYS,
                ));
        } catch (Throwable $e) {
            report($e);

            return [
                'ok' => false,
                'error' => app()->hasDebugModeEnabled()
                    ? 'No se pudo encolar el correo: '.$e->getMessage()
                    : 'No se pudo enviar el correo.',
                'sent' => false,
                'skipped' => null,
            ];
        }

        $subscription->update([
            'win_back_pending_at' => now(),
            'win_back_accepted_at' => null,
            'win_back_phone' => null,
            'win_back_email' => $email,
            'win_back_token' => $token,
            'win_back_channel' => 'email',
        ]);

        Log::info('FreeWinBack: oferta enviada', [
            'subscription_id' => $subscription->id,
            'tenant_id' => $tenant->id,
            'email' => $email,
        ]);

        return ['ok' => true, 'error' => null, 'sent' => true, 'skipped' => null];
    }

    /**
     * @param  Collection<int, Subscription>|list<Subscription>  $subscriptions
     * @return array{sent: int, skipped: int, failed: int, errors: list<string>}
     */
    public function sendOffers(iterable $subscriptions, bool $force = false): array
    {
        $sent = 0;
        $skipped = 0;
        $failed = 0;
        $errors = [];

        foreach ($subscriptions as $subscription) {
            if (! $subscription instanceof Subscription) {
                continue;
            }

            $result = $this->sendOffer($subscription, $force);
            if ($result['sent']) {
                $sent++;
            } elseif ($result['ok'] && $result['skipped'] !== null) {
                $skipped++;
            } else {
                $failed++;
                if (is_string($result['error']) && $result['error'] !== '') {
                    $errors[] = $result['error'];
                }
            }
        }

        return compact('sent', 'skipped', 'failed', 'errors');
    }

    /**
     * @return array{status: string, message: string, login_url: string|null, granted_days: int|null}
     */
    public function acceptByToken(string $token): array
    {
        $token = trim($token);
        if ($token === '' || strlen($token) < 32) {
            return [
                'status' => 'invalid',
                'message' => 'Enlace inválido.',
                'login_url' => null,
                'granted_days' => null,
            ];
        }

        /** @var Subscription|null $subscription */
        $subscription = Subscription::query()
            ->with(['tenant', 'plan'])
            ->where('win_back_token', $token)
            ->where('win_back_channel', 'email')
            ->first();

        if ($subscription === null) {
            return [
                'status' => 'invalid',
                'message' => 'Este enlace no es válido o ya fue usado.',
                'login_url' => null,
                'granted_days' => null,
            ];
        }

        if ($subscription->win_back_accepted_at !== null) {
            $tenant = $subscription->tenant;
            $loginUrl = $tenant instanceof Tenant ? TenantSubdomainUrl::login($tenant) : null;

            return [
                'status' => 'already_accepted',
                'message' => 'Esta oferta ya fue aceptada. Revisa tu correo con los datos de acceso.',
                'login_url' => $loginUrl,
                'granted_days' => null,
            ];
        }

        if ($subscription->win_back_pending_at === null) {
            return [
                'status' => 'expired',
                'message' => 'La oferta ya no está pendiente. Pide un nuevo correo de reenganche.',
                'login_url' => null,
                'granted_days' => null,
            ];
        }

        // Oferta válida 14 días desde el envío
        if ($subscription->win_back_pending_at->lt(now()->subDays(14))) {
            $subscription->update([
                'win_back_pending_at' => null,
                'win_back_token' => null,
            ]);

            return [
                'status' => 'expired',
                'message' => 'Este enlace expiró. Solicita un nuevo correo de reenganche.',
                'login_url' => null,
                'granted_days' => null,
            ];
        }

        $tenant = $subscription->tenant;
        if (! $tenant instanceof Tenant) {
            return [
                'status' => 'error',
                'message' => 'No se encontró la clínica asociada.',
                'login_url' => null,
                'granted_days' => null,
            ];
        }

        $admin = $this->resolveAdminUser($tenant);
        if ($admin === null) {
            return [
                'status' => 'error',
                'message' => 'No encontramos el usuario administrador de la clínica. Contáctanos para ayudarte.',
                'login_url' => null,
                'granted_days' => null,
            ];
        }

        $plainPassword = Str::password(length: 14, symbols: false);
        $email = strtolower(trim((string) ($subscription->win_back_email ?: $tenant->email_admin ?: $admin->email)));

        try {
            $previousTeam = getPermissionsTeamId();
            setPermissionsTeamId((string) $tenant->id);

            try {
                $admin->forceFill([
                    'password' => $plainPassword,
                    'is_active' => true,
                    'must_change_password' => true,
                    'email_verified_at' => $admin->email_verified_at ?? now(),
                ])->save();
            } finally {
                setPermissionsTeamId($previousTeam);
            }

            $days = $this->winBack->acceptPendingOffer($subscription);

            $subscription->refresh();
            $subscription->update([
                'win_back_token' => null,
                'win_back_channel' => 'email',
                'win_back_email' => $email,
                'win_back_phone' => null,
            ]);

            // Si el tenant estaba suspended, reactivar
            if (in_array($tenant->estado, ['suspended', 'trial'], true) || $tenant->estado === 'active') {
                // leave active/trial as-is; only unsuspend
            }
            if ($tenant->estado === 'suspended') {
                $tenant->forceFill([
                    'estado' => 'active',
                    'suspended_at' => null,
                    'suspension_reason' => null,
                ])->save();
            }

            $loginUrl = TenantSubdomainUrl::login($tenant);

            Notification::route('mail', $email)
                ->notify(new FreeWinBackCredentialsNotification(
                    tenant: $tenant,
                    loginEmail: $email,
                    temporaryPassword: $plainPassword,
                    loginUrl: $loginUrl,
                    grantedDays: $days,
                ));

            Log::info('FreeWinBack: oferta aceptada', [
                'subscription_id' => $subscription->id,
                'tenant_id' => $tenant->id,
                'days' => $days,
            ]);

            return [
                'status' => 'accepted',
                'message' => "¡Listo! Activamos {$days} días gratis. Te enviamos el subdominio, correo y contraseña temporal a {$email}.",
                'login_url' => $loginUrl,
                'granted_days' => $days,
            ];
        } catch (Throwable $e) {
            report($e);

            return [
                'status' => 'error',
                'message' => 'No pudimos activar la oferta. Intenta de nuevo o contáctanos.',
                'login_url' => null,
                'granted_days' => null,
            ];
        }
    }

    private function skipReason(Subscription $subscription): ?string
    {
        if ($subscription->win_back_accepted_at !== null
            && $subscription->win_back_accepted_at->gt(now()->subDays(self::ACCEPTED_COOLDOWN_DAYS))) {
            return 'recently_accepted';
        }

        if ($subscription->win_back_pending_at !== null
            && $subscription->win_back_channel === 'email'
            && $subscription->win_back_accepted_at === null
            && $subscription->win_back_pending_at->gt(now()->subHours(self::PENDING_COOLDOWN_HOURS))) {
            return 'pending_cooldown';
        }

        return null;
    }

    private function resolveAdminUser(Tenant $tenant): ?User
    {
        $adminEmail = strtolower(trim((string) $tenant->email_admin));

        if ($adminEmail !== '') {
            $byEmail = User::query()
                ->where('tenant_id', $tenant->id)
                ->whereRaw('LOWER(email) = ?', [$adminEmail])
                ->first();

            if ($byEmail !== null) {
                return $byEmail;
            }
        }

        $previousTeam = getPermissionsTeamId();
        setPermissionsTeamId((string) $tenant->id);

        try {
            return User::query()
                ->where('tenant_id', $tenant->id)
                ->role('admin_clinica')
                ->orderBy('created_at')
                ->first();
        } finally {
            setPermissionsTeamId($previousTeam);
        }
    }
}
