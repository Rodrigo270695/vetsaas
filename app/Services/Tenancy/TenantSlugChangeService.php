<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Models\Tenant;
use App\Notifications\Tenancy\TenantSubdomainChangedNotification;
use App\Services\OpenWa\PlatformWhatsAppMessenger;
use App\Support\Tenancy\TenantSubdomainUrl;
use App\Support\WhatsApp\WhatsAppChatId;
use App\Tenancy\TenantManager;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Cambia el slug (subdominio) de un tenant y notifica por correo y WhatsApp.
 */
final class TenantSlugChangeService
{
    public function __construct(
        private readonly TenantManager $tenantManager,
        private readonly PlatformWhatsAppMessenger $whatsAppMessenger,
    ) {}

    /**
     * @return array{
     *     previous_slug: string,
     *     new_slug: string,
     *     email_sent: bool,
     *     whatsapp_sent: bool,
     *     warnings: list<string>
     * }
     */
    public function change(Tenant $tenant, string $newSlug): array
    {
        $newSlug = strtolower(trim($newSlug));
        $previousSlug = $tenant->slug;

        if ($tenant->estado === 'cancelled') {
            throw ValidationException::withMessages([
                'slug' => 'No se puede cambiar el subdominio de un tenant cancelado.',
            ]);
        }

        if ($previousSlug === $newSlug) {
            throw ValidationException::withMessages([
                'slug' => 'El subdominio nuevo debe ser distinto al actual.',
            ]);
        }

        $tenant->update(['slug' => $newSlug]);
        $tenant->refresh();

        $this->tenantManager->flushCacheFor($tenant);

        $warnings = [];
        $emailSent = $this->sendEmail($tenant, $previousSlug, $newSlug, $warnings);
        $whatsappSent = $this->sendWhatsApp($tenant, $previousSlug, $newSlug, $warnings);

        return [
            'previous_slug' => $previousSlug,
            'new_slug' => $newSlug,
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
        string $previousSlug,
        string $newSlug,
        array &$warnings,
    ): bool {
        $email = strtolower(trim((string) $tenant->email_admin));
        if ($email === '') {
            $warnings[] = 'No se envió correo: el tenant no tiene email de administrador.';

            return false;
        }

        try {
            Notification::sendNow(
                Notification::route('mail', $email),
                new TenantSubdomainChangedNotification($tenant, $previousSlug, $newSlug),
            );
        } catch (\Throwable $e) {
            $warnings[] = app()->hasDebugModeEnabled()
                ? 'No se pudo enviar el correo: '.$e->getMessage()
                : 'No se pudo enviar el correo de aviso.';

            return false;
        }

        return true;
    }

    /**
     * @param  list<string>  $warnings
     */
    private function sendWhatsApp(
        Tenant $tenant,
        string $previousSlug,
        string $newSlug,
        array &$warnings,
    ): bool {
        if (! $this->whatsAppMessenger->isReady()) {
            $warnings[] = 'WhatsApp de plataforma no conectado; el cambio se aplicó igual.';

            return false;
        }

        $chatId = WhatsAppChatId::fromPhone($tenant->telefono);
        if ($chatId === null) {
            $warnings[] = 'No se envió WhatsApp: el tenant no tiene teléfono válido.';

            return false;
        }

        $brand = $tenant->nombre_comercial ?: $tenant->razon_social;
        $message = implode("\n", [
            "Hola, te escribimos desde VetSaaS.",
            '',
            "El subdominio de {$brand} ha sido actualizado:",
            '',
            'Anterior: '.TenantSubdomainUrl::host($previousSlug),
            'Nuevo: '.TenantSubdomainUrl::host($newSlug),
            '',
            'Accede desde: '.TenantSubdomainUrl::login($newSlug),
            '',
            'Guarda el nuevo enlace; el anterior ya no funcionará.',
        ]);

        try {
            $this->whatsAppMessenger->sendText($chatId, $message);
        } catch (\Throwable $e) {
            $warnings[] = app()->hasDebugModeEnabled()
                ? 'No se pudo enviar WhatsApp: '.$e->getMessage()
                : 'No se pudo enviar el WhatsApp de aviso.';

            return false;
        }

        return true;
    }
}
