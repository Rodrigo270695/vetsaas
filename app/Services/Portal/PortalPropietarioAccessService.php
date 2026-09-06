<?php

declare(strict_types=1);

namespace App\Services\Portal;

use App\Models\ClinicSetting;
use App\Models\PortalPropietario;
use App\Models\PortalPropietarioSesion;
use App\Models\Propietario;
use App\Models\Tenant;
use App\Services\Clinica\ClinicalHistoryWhatsAppSender;
use App\Support\Portal\PortalPhoneMask;
use App\Support\WhatsApp\WhatsAppChatId;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Symfony\Component\HttpFoundation\Cookie;

final class PortalPropietarioAccessService
{
    public function __construct(
        private readonly ClinicalHistoryWhatsAppSender $whatsApp,
    ) {}

    public function invite(Propietario $propietario, Tenant $tenant, ?string $invitedById): string
    {
        $phone = $propietario->telefono;
        $chatId = WhatsAppChatId::fromPhone($phone);
        if ($chatId === null) {
            throw new RuntimeException('El titular no tiene un celular de WhatsApp válido.');
        }

        $portal = $this->firstOrCreate($propietario);
        $plainToken = bin2hex(random_bytes(32));
        $portal->forceFill([
            'invite_token' => $plainToken,
            'invite_expires_at' => now()->addDays((int) config('portal.invite_days', 30)),
            'telefono_snapshot' => $phone,
            'invited_by_id' => $invitedById,
        ])->save();

        $url = route('tenant.portal.entrar', [
            'tenant_subdomain' => $tenant->slug,
            'token' => $plainToken,
        ]);

        $clinic = ClinicSetting::current();
        $clinicName = trim((string) ($clinic->nombre_comercial ?: $clinic->razon_social))
            ?: (string) config('app.name');
        $ownerName = $propietario->displayName() ?: 'cliente';
        $pet = $propietario->pacientes()->where('activo', true)->orderBy('nombre')->value('nombre');
        $petBit = is_string($pet) && $pet !== '' ? " de {$pet}" : '';

        $message = "Hola {$ownerName} 👋\n\n"
            ."{$clinicName} te abre el acceso a tus mascotas{$petBit}.\n\n"
            ."Entra desde tu celular (puedes dejarlo como app):\n{$url}\n\n"
            .'La primera vez creas un PIN de 4 dígitos. No lo compartas.';

        $this->whatsApp->send($tenant, $chatId, $message);

        return $url;
    }

    public function findValidInvite(string $token): ?PortalPropietario
    {
        if ($token === '' || strlen($token) < 32) {
            return null;
        }

        $portal = PortalPropietario::query()
            ->where('invite_token', $token)
            ->with('propietario')
            ->first();

        if ($portal === null || ! $portal->inviteIsValid() || $portal->propietario === null) {
            return null;
        }

        if (! $portal->propietario->activo) {
            return null;
        }

        return $portal;
    }

    public function setPin(PortalPropietario $portal, string $pin, Request $request): Cookie
    {
        $this->assertPinFormat($pin);

        $portal->forceFill([
            'pin_hash' => Hash::make($pin),
            'pin_set_at' => now(),
            'pin_failed_attempts' => 0,
            'pin_locked_until' => null,
            'reset_code_hash' => null,
            'reset_code_expires_at' => null,
            'reset_failed_attempts' => 0,
        ])->save();

        return $this->issueSession($portal, $request);
    }

    public function unlock(PortalPropietario $portal, string $pin, Request $request): Cookie
    {
        $this->assertPinFormat($pin);

        if ($portal->isPinLocked()) {
            throw new RuntimeException('Demasiados intentos. Espera un momento o restablece el PIN.');
        }

        if (! $portal->hasPin() || ! Hash::check($pin, (string) $portal->pin_hash)) {
            $this->registerFailedPin($portal);
            throw new RuntimeException('PIN incorrecto.');
        }

        $portal->forceFill([
            'pin_failed_attempts' => 0,
            'pin_locked_until' => null,
        ])->save();

        return $this->issueSession($portal, $request);
    }

    public function sendResetCode(PortalPropietario $portal, Tenant $tenant): string
    {
        if (! $portal->hasPin()) {
            throw new RuntimeException('Todavía no hay un PIN para restablecer.');
        }

        $phone = $portal->propietario?->telefono ?: $portal->telefono_snapshot;
        $chatId = WhatsAppChatId::fromPhone($phone);
        if ($chatId === null) {
            throw new RuntimeException('No hay un celular válido para enviar el código.');
        }

        $now = now();
        $count = (int) $portal->reset_sent_hour_count;
        $last = $portal->reset_last_sent_at;
        if ($last !== null && $last->gt($now->copy()->subHour())) {
            $max = (int) config('portal.reset_max_per_hour', 3);
            if ($count >= $max) {
                throw new RuntimeException('Ya enviamos varios códigos. Prueba de nuevo en un rato.');
            }
        } else {
            $count = 0;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $portal->forceFill([
            'reset_code_hash' => Hash::make($code),
            'reset_code_expires_at' => $now->copy()->addMinutes((int) config('portal.reset_code_minutes', 10)),
            'reset_failed_attempts' => 0,
            'reset_last_sent_at' => $now,
            'reset_sent_hour_count' => $count + 1,
        ])->save();

        $clinic = ClinicSetting::current();
        $clinicName = trim((string) ($clinic->nombre_comercial ?: $clinic->razon_social))
            ?: (string) config('app.name');

        $this->whatsApp->send(
            $tenant,
            $chatId,
            "{$clinicName}: tu código para restablecer el PIN es *{$code}*. Vence en 10 minutos. Si no lo pediste, ignora este mensaje.",
        );

        return PortalPhoneMask::mask($phone);
    }

    public function confirmReset(PortalPropietario $portal, string $code, string $pin, Request $request): Cookie
    {
        $this->assertPinFormat($pin);
        $code = preg_replace('/\D+/', '', $code) ?? '';
        if (strlen($code) !== 6) {
            throw new RuntimeException('Ingresa el código de 6 dígitos.');
        }

        if (
            $portal->reset_code_hash === null
            || $portal->reset_code_expires_at === null
            || $portal->reset_code_expires_at->isPast()
        ) {
            throw new RuntimeException('El código venció. Pide uno nuevo.');
        }

        if ((int) $portal->reset_failed_attempts >= 5) {
            throw new RuntimeException('Demasiados intentos. Pide un código nuevo.');
        }

        if (! Hash::check($code, $portal->reset_code_hash)) {
            $portal->increment('reset_failed_attempts');
            throw new RuntimeException('Código incorrecto.');
        }

        $this->revokeAllSessions($portal);

        $portal->forceFill([
            'pin_hash' => Hash::make($pin),
            'pin_set_at' => now(),
            'pin_failed_attempts' => 0,
            'pin_locked_until' => null,
            'reset_code_hash' => null,
            'reset_code_expires_at' => null,
            'reset_failed_attempts' => 0,
        ])->save();

        return $this->issueSession($portal, $request);
    }

    public function issueSession(PortalPropietario $portal, Request $request): Cookie
    {
        $plain = bin2hex(random_bytes(32));
        $days = max(1, (int) config('portal.session_days', 90));

        PortalPropietarioSesion::query()->create([
            'portal_propietario_id' => $portal->id,
            'token_hash' => $this->hashToken($plain),
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255) ?: null,
            'expires_at' => now()->addDays($days),
            'last_seen_at' => now(),
        ]);

        return cookie(
            name: (string) config('portal.cookie', 'vetsaas_portal'),
            value: $plain,
            minutes: $days * 24 * 60,
            path: '/',
            secure: (bool) config('session.secure'),
            httpOnly: true,
            sameSite: 'lax',
        );
    }

    public function resolveSession(?string $plainToken): ?PortalPropietarioSesion
    {
        if ($plainToken === null || $plainToken === '') {
            return null;
        }

        $sesion = PortalPropietarioSesion::query()
            ->where('token_hash', $this->hashToken($plainToken))
            ->with(['portal.propietario'])
            ->first();

        if ($sesion === null || ! $sesion->isActive()) {
            return null;
        }

        $portal = $sesion->portal;
        if ($portal === null || ! $portal->hasPin() || $portal->propietario === null || ! $portal->propietario->activo) {
            return null;
        }

        if ($sesion->last_seen_at === null || $sesion->last_seen_at->lt(now()->subMinutes(30))) {
            $sesion->forceFill(['last_seen_at' => now()])->save();
        }

        return $sesion;
    }

    public function forgetCookie(): Cookie
    {
        return cookie()->forget((string) config('portal.cookie', 'vetsaas_portal'));
    }

    public function revokeSession(?PortalPropietarioSesion $sesion): void
    {
        if ($sesion === null) {
            return;
        }

        $sesion->forceFill(['revoked_at' => now()])->save();
    }

    public function revokeAllSessions(PortalPropietario $portal): void
    {
        PortalPropietarioSesion::query()
            ->where('portal_propietario_id', $portal->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    private function firstOrCreate(Propietario $propietario): PortalPropietario
    {
        return PortalPropietario::query()->firstOrCreate(
            ['propietario_id' => $propietario->id],
            ['telefono_snapshot' => $propietario->telefono],
        );
    }

    private function registerFailedPin(PortalPropietario $portal): void
    {
        $attempts = (int) $portal->pin_failed_attempts + 1;
        $max = (int) config('portal.pin_max_attempts', 5);
        $lockUntil = $attempts >= $max
            ? now()->addMinutes((int) config('portal.pin_lock_minutes', 15))
            : null;

        $portal->forceFill([
            'pin_failed_attempts' => $attempts,
            'pin_locked_until' => $lockUntil,
        ])->save();
    }

    private function assertPinFormat(string $pin): void
    {
        if (! preg_match('/^\d{4}$/', $pin)) {
            throw new RuntimeException('El PIN debe tener 4 números.');
        }
    }

    private function hashToken(string $plain): string
    {
        return hash('sha256', $plain);
    }
}
