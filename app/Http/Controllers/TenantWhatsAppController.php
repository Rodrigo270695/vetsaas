<?php

namespace App\Http\Controllers;

use App\Models\NotificationQueue;
use App\Models\TenantWhatsAppSession;
use App\Services\Notifications\NotificationQueueService;
use App\Services\Notifications\WhatsAppNotificationDispatcher;
use App\Services\OpenWa\OpenWaClient;
use App\Services\OpenWa\TenantWhatsAppSessionSync;
use App\Support\OpenWa\TenantWhatsAppPresenter;
use App\Support\WhatsApp\WhatsAppChatId;
use App\Tenancy\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TenantWhatsAppController extends Controller
{
    public function sync(
        Request $request,
        TenantManager $tenants,
        TenantWhatsAppSessionSync $sync,
        TenantWhatsAppPresenter $presenter,
    ): RedirectResponse|JsonResponse {
        $tenant = $tenants->current()?->tenant;
        abort_if($tenant === null, 404);

        $session = $sync->ensureForTenant($tenant, wakeForLink: true);

        // Usuario pidió conectar: reactivar reconnect y despertar (sin QR si hay auth).
        if ($session !== null) {
            $session = $sync->enableAutoReconnect($session);
            $session = $sync->ensureForTenant($tenant, wakeForLink: true) ?? $session;
        }

        if ($request->expectsJson()) {
            return response()->json([
                'whatsapp' => $presenter->forTenant($tenant),
            ]);
        }

        if ($session?->isReady()) {
            return back()->with('success', 'WhatsApp conectado y listo para enviar.');
        }

        return back()->with(
            'info',
            'Sesión sincronizada. Escanea el código QR para vincular el número de la clínica.',
        );
    }

    public function qr(
        Request $request,
        TenantManager $tenants,
        OpenWaClient $client,
        TenantWhatsAppSessionSync $sync,
    ): JsonResponse {
        abort_unless($client->isConfigured(), 503, 'OpenWA no está configurado en el servidor.');

        $tenant = $tenants->current()?->tenant;
        abort_if($tenant === null, 404);

        $session = $sync->ensureForTenant($tenant, wakeForLink: true);
        abort_if($session === null, 422, 'No se pudo crear la sesión de WhatsApp.');

        $session = $sync->enableAutoReconnect($session);

        if (! $session->isReady()) {
            try {
                $remote = $client->getSession($session->openwa_session_id);
                $status = (string) ($remote['status'] ?? $session->status);

                // Solo despertar si está caída. NO reiniciar en initializing/authenticating:
                // eso corta Baileys antes de emitir el QR.
                if (in_array($status, ['created', 'disconnected', 'failed'], true)) {
                    $client->tryStartIfDown($session->openwa_session_id, $status);
                }
            } catch (\Throwable) {
                // Continúa e intenta obtener QR o refrescar estado.
            }
        }

        try {
            $session = $sync->refresh($session);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'ready' => false,
                'status' => $session->status,
                'qr_code' => null,
                'error' => 'No se pudo sincronizar la sesión con OpenWA. Revisa OPENWA_API_KEY.',
            ], 503);
        }

        if ($session->isReady()) {
            return response()->json([
                'ready' => true,
                'phone' => $session->phone,
                'status' => $session->status,
            ]);
        }

        try {
            $qr = $client->getQrCode($session->openwa_session_id);
            $qrCode = $qr['qrCode'] ?? null;

            return response()->json([
                'ready' => false,
                'status' => (string) ($qr['status'] ?? $session->status),
                'qr_code' => is_string($qrCode) && $qrCode !== '' ? $qrCode : null,
                'session_id' => $session->openwa_session_id,
                'message' => filled($qrCode)
                    ? null
                    : 'Esperando código QR de WhatsApp…',
            ]);
        } catch (\Throwable $e) {
            report($e);

            $status = (string) $session->status;
            $waiting = in_array($status, ['initializing', 'authenticating', 'created'], true);

            return response()->json([
                'ready' => false,
                'status' => $status,
                'qr_code' => null,
                'session_id' => $session->openwa_session_id,
                'message' => $waiting ? 'Iniciando sesión… el QR aparece en unos segundos.' : null,
                'error' => $waiting ? null : 'No se pudo obtener el código QR.',
            ], $waiting ? 200 : 503);
        }
    }

    public function logout(
        TenantManager $tenants,
        TenantWhatsAppSessionSync $sync,
    ): RedirectResponse {
        $tenant = $tenants->current()?->tenant;
        abort_if($tenant === null, 404);

        $session = TenantWhatsAppSession::query()
            ->where('tenant_id', $tenant->id)
            ->first();
        abort_if($session === null || ! $session->isReady(), 422, 'No hay WhatsApp conectado para desvincular.');

        try {
            $sync->disconnect($session);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'No se pudo desvincular WhatsApp. Intenta de nuevo o contacta a soporte.');
        }

        return back()->with('success', 'WhatsApp desvinculado. Puedes conectar otro número cuando quieras.');
    }

    public function sendTest(
        Request $request,
        TenantManager $tenants,
        NotificationQueueService $queue,
        WhatsAppNotificationDispatcher $dispatcher,
    ): RedirectResponse {
        $data = $request->validate([
            'destinatario' => ['required', 'string', 'max:30'],
            'mensaje' => ['required', 'string', 'max:1000'],
        ]);

        $tenant = $tenants->current()?->tenant;
        abort_if($tenant === null, 404);

        $chatId = WhatsAppChatId::fromPhone($data['destinatario']);
        if ($chatId === null) {
            return back()
                ->withErrors(['destinatario' => 'Ingresa un número válido (ej. 987654321 o 51987654321).'])
                ->withInput();
        }

        $session = TenantWhatsAppSession::query()
            ->where('tenant_id', $tenant->id)
            ->first();

        if ($session !== null && ! $session->isReady()) {
            $session = app(TenantWhatsAppSessionSync::class)->refresh($session);
        }

        $sessionChatId = WhatsAppChatId::fromPhone($session?->phone);
        if ($sessionChatId !== null && $sessionChatId === $chatId) {
            return back()
                ->withErrors([
                    'destinatario' => 'Usa otro número: no se puede enviar la prueba al mismo WhatsApp vinculado a la clínica.',
                ])
                ->withInput();
        }

        $item = $queue->enqueue(
            tipo: 'prueba',
            destinatario: $chatId,
            cuerpo: $data['mensaje'],
            enviarAt: now(),
            destinatarioNombre: null,
            prioridad: 1,
        );

        if (! $item instanceof NotificationQueue) {
            return back()->with('error', 'No se pudo encolar el mensaje de prueba.');
        }

        try {
            $dispatcher->dispatchOne($item, $tenant);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Error al enviar: '.$e->getMessage());
        }

        $item->refresh();

        if ($item->estado === NotificationQueue::ESTADO_ENVIADO) {
            return back()->with('success', 'Mensaje de prueba enviado. Revisa el histórico o el teléfono destino.');
        }

        return back()->with(
            'error',
            $item->error_mensaje ?? 'No se pudo enviar el mensaje. Verifica que WhatsApp siga conectado.',
        );
    }
}
