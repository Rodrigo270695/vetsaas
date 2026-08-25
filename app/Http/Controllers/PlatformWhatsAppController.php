<?php

namespace App\Http\Controllers;

use App\Models\PlatformWhatsAppSession;
use App\Services\OpenWa\OpenWaClient;
use App\Services\OpenWa\PlatformWhatsAppMessenger;
use App\Services\OpenWa\PlatformWhatsAppSessionSync;
use App\Support\OpenWa\PlatformWhatsAppPresenter;
use App\Support\WhatsApp\WhatsAppChatId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PlatformWhatsAppController extends Controller
{
    public function sync(
        Request $request,
        PlatformWhatsAppSessionSync $sync,
        PlatformWhatsAppPresenter $presenter,
    ): RedirectResponse|JsonResponse {
        $session = $sync->ensure(wakeForLink: true);

        // El usuario pidió conectar: reactivar auto-reconnect y despertar Chromium
        // (auth en disco → ready sin QR; si no hay auth → qr_ready).
        if ($session !== null) {
            $session = $sync->enableAutoReconnect($session);
            $session = $sync->ensure(wakeForLink: true) ?? $session;
        }

        if ($request->expectsJson()) {
            return response()->json([
                'whatsapp' => $presenter->present(),
            ]);
        }

        if ($session?->isReady()) {
            return back()->with('success', 'WhatsApp de plataforma conectado y listo.');
        }

        return back()->with(
            'info',
            'Sesión sincronizada. Escanea el código QR para vincular el número de Orvae.',
        );
    }

    public function qr(
        OpenWaClient $client,
        PlatformWhatsAppSessionSync $sync,
    ): JsonResponse {
        abort_unless($client->isConfigured(), 503, 'OpenWA no está configurado en el servidor.');

        $session = $sync->ensure(wakeForLink: true);
        abort_if($session === null, 422, 'No se pudo crear la sesión de WhatsApp de plataforma.');

        $session = $sync->enableAutoReconnect($session);

        if (! $session->isReady()) {
            try {
                $remote = $client->getSession($session->openwa_session_id);
                $status = (string) ($remote['status'] ?? $session->status);

                // Solo despertar si está caída. NO reiniciar en initializing/authenticating.
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
            $status = (string) $session->status;
            $waiting = in_array($status, ['initializing', 'authenticating', 'created'], true);

            return response()->json([
                'ready' => false,
                'status' => $status,
                'qr_code' => null,
                'session_id' => $session->openwa_session_id,
                'message' => $waiting ? 'Iniciando sesión… el QR aparece en unos segundos.' : null,
                'error' => $waiting ? null : $e->getMessage(),
            ], $waiting ? 200 : 422);
        }
    }

    public function logout(PlatformWhatsAppSessionSync $sync): RedirectResponse
    {
        $session = PlatformWhatsAppSession::query()
            ->where('openwa_session_name', $sync->sessionName())
            ->first();
        abort_if($session === null || ! $session->isReady(), 422, 'No hay WhatsApp de plataforma conectado.');

        try {
            $sync->disconnect($session);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'No se pudo desvincular WhatsApp. Intenta de nuevo.');
        }

        return back()->with('success', 'WhatsApp de plataforma desvinculado.');
    }

    public function sendTest(
        Request $request,
        PlatformWhatsAppMessenger $messenger,
    ): RedirectResponse {
        $data = $request->validate([
            'destinatario' => ['required', 'string', 'max:30'],
            'mensaje' => ['required', 'string', 'max:1000'],
        ]);

        $chatId = WhatsAppChatId::fromPhone($data['destinatario']);
        if ($chatId === null) {
            return back()
                ->withErrors(['destinatario' => 'Ingresa un número válido (ej. 987654321 o 51987654321).'])
                ->withInput();
        }

        try {
            $messenger->sendText($chatId, $data['mensaje']);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Error al enviar: '.$e->getMessage());
        }

        return back()->with('success', 'Mensaje de prueba enviado desde la sesión de plataforma.');
    }
}
