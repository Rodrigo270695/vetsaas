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
use App\Support\WhatsApp\WhatsAppChatId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

/**
 * Reenganche Free vencidos: WhatsApp si hay celular; si no hay o falla → email.
 * Aceptación: botón del correo / link del WA, o «Sí» por WhatsApp.
 */
final class FreePlanWinBackService
{
    public const OFFER_DAYS = 30;

    public const PENDING_COOLDOWN_HOURS = 72;

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
                $q->where('estado', '!=', 'cancelled')
                    ->where(function (Builder $contact): void {
                        $contact
                            ->where(function (Builder $email): void {
                                $email->whereNotNull('email_admin')
                                    ->where('email_admin', '!=', '');
                            })
                            ->orWhere(function (Builder $phone): void {
                                $phone->whereNotNull('telefono')
                                    ->where('telefono', '!=', '');
                            });
                    });
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

        return $this->filterExpired(
            $this->scopeEligibleFreeExpired(Subscription::query())
                ->with(['tenant', 'plan'])
                ->whereIn('tenant_id', $ids)
                ->orderByDesc('updated_at')
                ->get()
                ->unique('tenant_id'),
        );
    }

    /**
     * @return Collection<int, Subscription>
     */
    public function findAllEligibleFreeExpired(int $limit = 500): Collection
    {
        return $this->filterExpired(
            $this->scopeEligibleFreeExpired(Subscription::query())
                ->with(['tenant', 'plan'])
                ->orderByDesc('updated_at')
                ->limit(max(1, min($limit, 1000)))
                ->get()
                ->unique('tenant_id'),
        );
    }

    /**
     * @return array{ok: bool, error: string|null, sent: bool, skipped: string|null, channel: string|null}
     */
    public function sendOffer(Subscription $subscription, bool $force = false): array
    {
        $subscription->loadMissing(['tenant', 'plan']);

        if ($subscription->plan?->codigo !== Plan::CODIGO_FREE) {
            return ['ok' => false, 'error' => 'Solo aplica a plan Free.', 'sent' => false, 'skipped' => 'not_free', 'channel' => null];
        }

        if ($subscription->estado === 'cancelled' || $subscription->cancelled_at !== null) {
            return ['ok' => false, 'error' => 'Suscripción cancelada.', 'sent' => false, 'skipped' => 'cancelled', 'channel' => null];
        }

        $tenant = $subscription->tenant;
        if (! $tenant instanceof Tenant) {
            return ['ok' => false, 'error' => 'Tenant no encontrado.', 'sent' => false, 'skipped' => 'no_tenant', 'channel' => null];
        }

        $email = strtolower(trim((string) $tenant->email_admin));
        $emailOk = $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL);
        $hasPhone = WhatsAppChatId::fromPhone($tenant->telefono) !== null;

        if (! $emailOk && ! $hasPhone) {
            return ['ok' => false, 'error' => 'Sin celular ni correo admin válido.', 'sent' => false, 'skipped' => 'no_contact', 'channel' => null];
        }

        if (! $force) {
            $skip = $this->skipReason($subscription);
            if ($skip !== null) {
                return ['ok' => true, 'error' => null, 'sent' => false, 'skipped' => $skip, 'channel' => null];
            }

            $daysUntil = SubscriptionExpiry::daysUntil(
                SubscriptionExpiry::anchor($subscription, $tenant),
            );
            if ($daysUntil === null || $daysUntil >= 0) {
                return ['ok' => true, 'error' => null, 'sent' => false, 'skipped' => 'not_expired', 'channel' => null];
            }
        }

        $token = Str::random(48);
        $acceptUrl = url('/win-back/free/'.$token);

        // 1) Celular → WhatsApp
        if ($hasPhone) {
            $waMessage = $this->whatsAppOfferMessage($tenant, $acceptUrl);
            $wa = $this->winBack->send($subscription, $waMessage, true);

            if ($wa['ok']) {
                $subscription->refresh();
                $subscription->update([
                    'win_back_channel' => 'whatsapp',
                    'win_back_token' => $token,
                    'win_back_email' => $emailOk ? $email : null,
                    'win_back_accepted_at' => null,
                ]);

                Log::info('FreeWinBack: oferta enviada por WhatsApp', [
                    'subscription_id' => $subscription->id,
                    'tenant_id' => $tenant->id,
                ]);

                return ['ok' => true, 'error' => null, 'sent' => true, 'skipped' => null, 'channel' => 'whatsapp'];
            }

            Log::warning('FreeWinBack: WhatsApp falló, intento email', [
                'subscription_id' => $subscription->id,
                'error' => $wa['error'],
            ]);
        }

        // 2) Fallback email
        if (! $emailOk) {
            return [
                'ok' => false,
                'error' => $hasPhone
                    ? 'WhatsApp falló y no hay correo admin para respaldo.'
                    : 'Sin correo admin válido.',
                'sent' => false,
                'skipped' => null,
                'channel' => null,
            ];
        }

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
                'channel' => null,
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

        Log::info('FreeWinBack: oferta enviada por email', [
            'subscription_id' => $subscription->id,
            'tenant_id' => $tenant->id,
            'email' => $email,
            'wa_fallback' => $hasPhone,
        ]);

        return ['ok' => true, 'error' => null, 'sent' => true, 'skipped' => null, 'channel' => 'email'];
    }

    /**
     * @param  Collection<int, Subscription>|list<Subscription>  $subscriptions
     * @return array{sent: int, skipped: int, failed: int, errors: list<string>, via_whatsapp: int, via_email: int}
     */
    public function sendOffers(iterable $subscriptions, bool $force = false): array
    {
        $sent = 0;
        $skipped = 0;
        $failed = 0;
        $viaWhatsapp = 0;
        $viaEmail = 0;
        $errors = [];

        foreach ($subscriptions as $subscription) {
            if (! $subscription instanceof Subscription) {
                continue;
            }

            $result = $this->sendOffer($subscription, $force);
            if ($result['sent']) {
                $sent++;
                if ($result['channel'] === 'whatsapp') {
                    $viaWhatsapp++;
                }
                if ($result['channel'] === 'email') {
                    $viaEmail++;
                }
            } elseif ($result['ok'] && $result['skipped'] !== null) {
                $skipped++;
            } else {
                $failed++;
                if (is_string($result['error']) && $result['error'] !== '') {
                    $errors[] = $result['error'];
                }
            }
        }

        return [
            'sent' => $sent,
            'skipped' => $skipped,
            'failed' => $failed,
            'errors' => $errors,
            'via_whatsapp' => $viaWhatsapp,
            'via_email' => $viaEmail,
        ];
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
            ->whereIn('win_back_channel', ['email', 'whatsapp'])
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
                'message' => 'Esta oferta ya fue aceptada. Revisa tu correo o WhatsApp con los datos de acceso.',
                'login_url' => $loginUrl,
                'granted_days' => null,
            ];
        }

        if ($subscription->win_back_pending_at === null) {
            return [
                'status' => 'expired',
                'message' => 'La oferta ya no está pendiente. Pide un nuevo reenganche.',
                'login_url' => null,
                'granted_days' => null,
            ];
        }

        if ($subscription->win_back_pending_at->lt(now()->subDays(14))) {
            $subscription->update([
                'win_back_pending_at' => null,
                'win_back_token' => null,
            ]);

            return [
                'status' => 'expired',
                'message' => 'Este enlace expiró. Solicita un nuevo reenganche.',
                'login_url' => null,
                'granted_days' => null,
            ];
        }

        return $this->completeAcceptance($subscription);
    }

    /**
     * Tras «Sí» por WhatsApp (SalesBot / Conversaciones).
     *
     * @return array{ok: bool, login_url: string|null, granted_days: int|null}
     */
    public function completeAcceptanceAfterWhatsAppReply(Subscription $subscription): array
    {
        $subscription->loadMissing(['tenant', 'plan']);

        if ($subscription->plan?->codigo !== Plan::CODIGO_FREE) {
            return ['ok' => false, 'login_url' => null, 'granted_days' => null];
        }

        // acceptPendingOffer ya se llamó desde tryHandleInbound
        $result = $this->deliverCredentials($subscription->fresh(['tenant', 'plan']) ?? $subscription);

        return [
            'ok' => $result['ok'],
            'login_url' => $result['login_url'],
            'granted_days' => $result['granted_days'],
        ];
    }

    /**
     * @return array{status: string, message: string, login_url: string|null, granted_days: int|null}
     */
    private function completeAcceptance(Subscription $subscription): array
    {
        $channel = (string) ($subscription->win_back_channel ?? 'email');
        $days = $this->winBack->acceptPendingOffer($subscription);
        $subscription->refresh();

        $creds = $this->deliverCredentials($subscription, $channel);

        if (! $creds['ok']) {
            return [
                'status' => 'error',
                'message' => $creds['error'] ?? 'No pudimos activar la oferta. Intenta de nuevo o contáctanos.',
                'login_url' => $creds['login_url'],
                'granted_days' => $days,
            ];
        }

        return [
            'status' => 'accepted',
            'message' => $creds['message'] ?? "¡Listo! Activamos {$days} días gratis.",
            'login_url' => $creds['login_url'],
            'granted_days' => $days,
        ];
    }

    /**
     * Genera password temporal y envía credenciales (email; si no hay, WhatsApp).
     *
     * @return array{ok: bool, error: string|null, message: string|null, login_url: string|null, granted_days: int|null}
     */
    public function deliverCredentials(Subscription $subscription, ?string $preferChannel = null): array
    {
        $subscription->loadMissing(['tenant', 'plan']);
        $tenant = $subscription->tenant;

        if (! $tenant instanceof Tenant) {
            return ['ok' => false, 'error' => 'No se encontró la clínica.', 'message' => null, 'login_url' => null, 'granted_days' => null];
        }

        $admin = $this->resolveAdminUser($tenant);
        if ($admin === null) {
            return [
                'ok' => false,
                'error' => 'No encontramos el usuario administrador de la clínica.',
                'message' => null,
                'login_url' => null,
                'granted_days' => null,
            ];
        }

        $plainPassword = Str::password(length: 14, symbols: false);
        $email = strtolower(trim((string) ($subscription->win_back_email ?: $tenant->email_admin ?: $admin->email)));
        $emailOk = $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL);
        $loginUrl = TenantSubdomainUrl::login($tenant);
        $days = self::OFFER_DAYS;

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

            if ($tenant->estado === 'suspended') {
                $tenant->forceFill([
                    'estado' => 'active',
                    'suspended_at' => null,
                    'suspension_reason' => null,
                ])->save();
            }

            $subscription->update([
                'win_back_token' => null,
                'win_back_email' => $emailOk ? $email : $subscription->win_back_email,
                'win_back_channel' => $preferChannel ?? $subscription->win_back_channel ?? 'email',
            ]);

            if ($emailOk) {
                Notification::route('mail', $email)
                    ->notify(new FreeWinBackCredentialsNotification(
                        tenant: $tenant,
                        loginEmail: $email,
                        temporaryPassword: $plainPassword,
                        loginUrl: $loginUrl,
                        grantedDays: $days,
                    ));
            }

            // Si no hay email, o además queremos confirmar por WA cuando el canal fue WhatsApp
            $chatId = WhatsAppChatId::fromPhone($tenant->telefono);
            if ($chatId !== null && (! $emailOk || ($preferChannel ?? $subscription->win_back_channel) === 'whatsapp')) {
                $credsWa = implode("\n", [
                    '¡Listo! 🎉 Ya activamos 1 mes gratis de VetSaaS.',
                    '',
                    'Acceso:',
                    '• Subdominio: '.$tenant->slug,
                    '• Correo: '.($emailOk ? $email : (string) $admin->email),
                    '• Contraseña temporal: '.$plainPassword,
                    '',
                    'Entra aquí: '.$loginUrl,
                    'Cambia la contraseña al iniciar sesión.',
                ]);

                try {
                    if ($this->winBack->messengerIsReady()) {
                        $this->winBack->sendRawText($chatId, $credsWa);
                    }
                } catch (Throwable $e) {
                    report($e);
                    if (! $emailOk) {
                        return [
                            'ok' => false,
                            'error' => 'Se activó el mes pero no pudimos enviar las credenciales.',
                            'message' => null,
                            'login_url' => $loginUrl,
                            'granted_days' => $days,
                        ];
                    }
                }
            }

            Log::info('FreeWinBack: credenciales entregadas', [
                'subscription_id' => $subscription->id,
                'tenant_id' => $tenant->id,
                'via_email' => $emailOk,
            ]);

            $msg = $emailOk
                ? "¡Listo! Activamos {$days} días gratis. Te enviamos el subdominio, correo y contraseña temporal a {$email}."
                : "¡Listo! Activamos {$days} días gratis. Te enviamos las credenciales por WhatsApp.";

            return [
                'ok' => true,
                'error' => null,
                'message' => $msg,
                'login_url' => $loginUrl,
                'granted_days' => $days,
            ];
        } catch (Throwable $e) {
            report($e);

            return [
                'ok' => false,
                'error' => 'No pudimos completar la activación.',
                'message' => null,
                'login_url' => null,
                'granted_days' => null,
            ];
        }
    }

    private function whatsAppOfferMessage(Tenant $tenant, string $acceptUrl): string
    {
        $name = trim((string) ($tenant->nombre_comercial ?: $tenant->razon_social ?: 'equipo'));

        return implode("\n", [
            "Hola, {$name} 👋",
            '',
            'Tu periodo Free de VetSaaS ya venció y queremos que vuelvas a probar con tranquilidad.',
            '',
            'Novedades: chat interno del equipo, plantillas de WhatsApp y programa de referidos.',
            '',
            'Te regalamos 1 mes gratis. Elige una opción:',
            '1) Toca este enlace: '.$acceptUrl,
            '2) O responde «Sí» / «Acepto» a este chat.',
            '',
            '— Equipo Orvae / VetSaaS',
        ]);
    }

    /**
     * @param  Collection<int, Subscription>  $subs
     * @return Collection<int, Subscription>
     */
    private function filterExpired(Collection $subs): Collection
    {
        return $subs
            ->filter(function (Subscription $subscription): bool {
                $tenant = $subscription->tenant;
                $days = SubscriptionExpiry::daysUntil(
                    SubscriptionExpiry::anchor($subscription, $tenant),
                );

                return $days !== null && $days < 0;
            })
            ->values();
    }

    private function skipReason(Subscription $subscription): ?string
    {
        if ($subscription->win_back_accepted_at !== null
            && $subscription->win_back_accepted_at->gt(now()->subDays(self::ACCEPTED_COOLDOWN_DAYS))) {
            return 'recently_accepted';
        }

        if ($subscription->win_back_pending_at !== null
            && in_array($subscription->win_back_channel, ['email', 'whatsapp'], true)
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
