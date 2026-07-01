<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\OpenWa\TenantWhatsAppSessionAdmin;
use Illuminate\Http\RedirectResponse;

class TenantWhatsAppPlatformController extends Controller
{
    public function restart(Tenant $tenant, TenantWhatsAppSessionAdmin $admin): RedirectResponse
    {
        abort_unless(in_array($tenant->estado, ['trial', 'active', 'suspended'], true), 422);

        try {
            $result = $admin->restart($tenant);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $this->flashMessage('reiniciada', $result));
    }

    public function stop(Tenant $tenant, TenantWhatsAppSessionAdmin $admin): RedirectResponse
    {
        abort_unless(in_array($tenant->estado, ['trial', 'active', 'suspended'], true), 422);

        try {
            $result = $admin->stop($tenant);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $this->flashMessage('detenida', $result));
    }

    /**
     * @param  array{session: \App\Models\TenantWhatsAppSession, has_qr: bool, qr_status: string|null, warnings: list<string>}  $result
     */
    private function flashMessage(string $action, array $result): string
    {
        $session = $result['session'];
        $parts = [
            sprintf(
                'Sesión WhatsApp %s (%s). Estado: %s',
                $action,
                $session->openwa_session_name,
                $session->status,
            ),
        ];

        if ($session->phone) {
            $parts[] = 'Teléfono: '.$session->phone;
        }

        if ($result['has_qr']) {
            $parts[] = 'QR disponible: el admin de la clínica puede escanearlo en Comunicaciones → Cola saliente.';
        } elseif ($session->isReady()) {
            $parts[] = 'Lista para enviar mensajes.';
        } elseif ($result['qr_status'] !== null) {
            $parts[] = 'Estado QR: '.$result['qr_status'];
        }

        if ($result['warnings'] !== []) {
            $parts[] = 'Avisos: '.implode('; ', $result['warnings']);
        }

        return implode(' ', $parts);
    }
}
