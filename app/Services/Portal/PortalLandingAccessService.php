<?php

declare(strict_types=1);

namespace App\Services\Portal;

use App\Models\ClinicSetting;
use App\Models\PortalPropietario;
use App\Models\Propietario;
use App\Models\Tenant;
use App\Services\Clinica\ClinicalHistoryWhatsAppSender;
use App\Support\Portal\PortalLandingDocument;
use App\Support\WhatsApp\WhatsAppChatId;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

final class PortalLandingAccessService
{
    public function __construct(
        private readonly ClinicalHistoryWhatsAppSender $whatsApp,
    ) {}

    public function landingEnabled(): bool
    {
        return (bool) (ClinicSetting::current()->portal_landing_activo ?? false);
    }

    public function findPropietarioByDocument(string $numeroDocumento): ?Propietario
    {
        $norm = PortalLandingDocument::normalize($numeroDocumento);
        if (! PortalLandingDocument::isPlausible($norm)) {
            return null;
        }

        return Propietario::query()
            ->where('activo', true)
            ->whereNotNull('numero_documento')
            ->whereRaw("regexp_replace(numero_documento, '\\D', '', 'g') = ?", [$norm])
            ->first();
    }

    public function portalForPropietario(Propietario $propietario): PortalPropietario
    {
        return PortalPropietario::query()->firstOrCreate(
            ['propietario_id' => $propietario->id],
            ['telefono_snapshot' => $propietario->telefono],
        );
    }

    public function sendLandingOtp(PortalPropietario $portal, Tenant $tenant): string
    {
        $phone = $portal->propietario?->telefono ?: $portal->telefono_snapshot;
        $chatId = WhatsAppChatId::fromPhone($phone);
        if ($chatId === null) {
            throw new RuntimeException('No hay un celular válido en la ficha del titular.');
        }

        $now = now();
        $count = (int) $portal->landing_otp_sent_hour_count;
        $last = $portal->landing_otp_last_sent_at;
        $max = (int) config('portal.landing_otp_max_per_hour', 5);
        if ($last !== null && $last->gt($now->copy()->subHour())) {
            if ($count >= $max) {
                throw new RuntimeException('Demasiados intentos. Prueba de nuevo en unos minutos.');
            }
        } else {
            $count = 0;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $minutes = max(1, (int) config('portal.landing_otp_minutes', 10));

        $portal->forceFill([
            'landing_otp_hash' => Hash::make($code),
            'landing_otp_expires_at' => $now->copy()->addMinutes($minutes),
            'landing_otp_failed_attempts' => 0,
            'landing_otp_last_sent_at' => $now,
            'landing_otp_sent_hour_count' => $count + 1,
        ])->save();

        $clinic = ClinicSetting::current();
        $clinicName = trim((string) ($clinic->nombre_comercial ?: $clinic->razon_social))
            ?: (string) config('app.name');

        $this->whatsApp->send(
            $tenant,
            $chatId,
            "{$clinicName}: tu código para entrar al portal de mascotas es *{$code}*. Vence en {$minutes} minutos. Si no lo pediste, ignora este mensaje.",
        );

        return \App\Support\Portal\PortalPhoneMask::mask($phone);
    }

    public function verifyLandingOtp(PortalPropietario $portal, string $code): void
    {
        $code = preg_replace('/\D+/', '', $code) ?? '';
        if (strlen($code) !== 6) {
            throw new RuntimeException('Ingresa el código de 6 dígitos.');
        }

        if (
            $portal->landing_otp_hash === null
            || $portal->landing_otp_expires_at === null
            || $portal->landing_otp_expires_at->isPast()
        ) {
            throw new RuntimeException('El código venció. Vuelve a ingresar tu documento.');
        }

        if ((int) $portal->landing_otp_failed_attempts >= 5) {
            throw new RuntimeException('Demasiados intentos. Vuelve a ingresar tu documento.');
        }

        if (! Hash::check($code, (string) $portal->landing_otp_hash)) {
            $portal->increment('landing_otp_failed_attempts');
            throw new RuntimeException('Código incorrecto.');
        }

        $portal->forceFill([
            'landing_otp_hash' => null,
            'landing_otp_expires_at' => null,
            'landing_otp_failed_attempts' => 0,
        ])->save();
    }
}