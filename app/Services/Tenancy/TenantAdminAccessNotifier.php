<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Models\Tenant;
use App\Notifications\Tenancy\TenantAdminAccessRecoveredNotification;
use App\Services\OpenWa\PlatformWhatsAppMessenger;
use App\Support\Tenancy\TenantSubdomainUrl;
use App\Support\WhatsApp\WhatsAppChatId;
use Illuminate\Support\Facades\Notification;

/**
 * Avisa al cliente (correo + WhatsApp de la ficha del tenant) tras recuperar acceso admin.
 */
final class TenantAdminAccessNotifier
{
    public function __construct(
        private readonly PlatformWhatsAppMessenger $whatsAppMessenger,
    ) {}

    /**
     * @return array{email_sent: bool, whatsapp_sent: bool, warnings: list<string>}
     */
    public function notify(Tenant $tenant, string $adminEmail, ?string $bootstrapUrl): array
    {
        $warnings = [];
        $loginUrl = TenantSubdomainUrl::login($tenant);

        $emailSent = $this->sendEmail($tenant, $adminEmail, $loginUrl, $bootstrapUrl, $warnings);
        $whatsappSent = $this->sendWhatsApp($tenant, $adminEmail, $loginUrl, $bootstrapUrl, $warnings);

        return [
            'email_sent' => $emailSent,
            'whatsapp_sent' => $whatsappSent,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  list<string>  $warnings
     */
    private function sendEmail(
        Tenant $tenant,
        string $adminEmail,
        string $loginUrl,
        ?string $bootstrapUrl,
        array &$warnings,
    ): bool {
        $email = strtolower(trim($adminEmail));
        if ($email === '') {
            $warnings[] = 'No se envió correo: no hay email de administrador.';

            return false;
        }

        try {
            Notification::sendNow(
                Notification::route('mail', $email),
                new TenantAdminAccessRecoveredNotification(
                    $tenant,
                    $email,
                    $loginUrl,
                    $bootstrapUrl,
                ),
            );
        } catch (\Throwable $e) {
            $warnings[] = app()->hasDebugModeEnabled()
                ? 'No se pudo enviar el correo: '.$e->getMessage()
                : 'No se pudo enviar el correo de acceso.';

            return false;
        }

        return true;
    }

    /**
     * @param  list<string>  $warnings
     */
    private function sendWhatsApp(
        Tenant $tenant,
        string $adminEmail,
        string $loginUrl,
        ?string $bootstrapUrl,
        array &$warnings,
    ): bool {
        if (! $this->whatsAppMessenger->isReady()) {
            $warnings[] = 'WhatsApp de plataforma no conectado; el acceso se guardó igual.';

            return false;
        }

        $chatId = WhatsAppChatId::fromPhone($tenant->telefono);
        if ($chatId === null) {
            $warnings[] = 'No se envió WhatsApp: el tenant no tiene teléfono válido.';

            return false;
        }

        $brand = $tenant->nombre_comercial ?: $tenant->razon_social;
        $lines = [
            'Hola, te escribimos desde VetSaaS.',
            '',
            "Restablecimos el acceso de administrador de {$brand}.",
            '',
            'Correo: '.$adminEmail,
            'Entrar: '.$loginUrl,
        ];

        if (is_string($bootstrapUrl) && $bootstrapUrl !== '') {
            $lines[] = '';
            $lines[] = 'Definir o restablecer contraseña (válido ~48h):';
            $lines[] = $bootstrapUrl;
            $lines[] = '';
            $lines[] = 'Si el enlace caduca, usa «Olvidé mi contraseña» en el login.';
        }

        try {
            $this->whatsAppMessenger->sendText($chatId, implode("\n", $lines));
        } catch (\Throwable $e) {
            $warnings[] = app()->hasDebugModeEnabled()
                ? 'No se pudo enviar WhatsApp: '.$e->getMessage()
                : 'No se pudo enviar el WhatsApp de acceso.';

            return false;
        }

        return true;
    }
}
