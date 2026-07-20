<?php

declare(strict_types=1);

namespace App\Services\Laboratorio;

use App\Models\ClinicSetting;
use App\Models\NotificationQueue;
use App\Models\PedidoLaboratorio;
use App\Models\PedidoLaboratorioLinea;
use App\Models\Tenant;
use App\Models\TenantWhatsAppSession;
use App\Services\Notifications\ReminderMessageBuilder;
use App\Services\OpenWa\OpenWaClient;
use App\Services\OpenWa\TenantWhatsAppSessionSync;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Envía los archivos de resultado de un pedido de laboratorio por WhatsApp.
 */
final class PedidoLaboratorioWhatsAppSender
{
    public function __construct(
        private readonly OpenWaClient $client,
        private readonly TenantWhatsAppSessionSync $sessionSync,
        private readonly ReminderMessageBuilder $messages,
    ) {}

    /**
     * @return array{sent: int, message_ids: list<string|null>}
     */
    public function send(
        PedidoLaboratorio $pedido,
        Tenant $tenant,
        string $chatId,
        string $recipientName,
        ClinicSetting $clinic,
    ): array {
        if (! $this->client->isConfigured()) {
            throw new RuntimeException('WhatsApp (OpenWA) no está configurado.');
        }

        $session = $this->resolveReadySession($tenant);
        if ($session === null) {
            throw new RuntimeException('La sesión WhatsApp de la clínica no está conectada.');
        }

        $pedido->loadMissing(['paciente:id,nombre', 'lineas']);

        $attachments = $this->resolveAttachments($pedido);
        if ($attachments === []) {
            throw new RuntimeException('Este pedido no tiene documentos de resultado para enviar.');
        }

        $clinicName = $this->messages->clinicDisplayName($clinic);
        $petName = $pedido->paciente?->nombre ?? 'tu mascota';
        $fecha = $pedido->solicitado_at
            ?->timezone(config('app.timezone'))
            ->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i');

        $examenes = collect($attachments)
            ->pluck('examen')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $intro = $this->messages->laboratorioResultados(
            $clinicName,
            $recipientName,
            $petName,
            $examenes,
            $fecha,
        );

        $sessionId = (string) $session->openwa_session_id;
        $messageIds = [];
        $sent = 0;
        $isFirst = true;

        foreach ($attachments as $attachment) {
            try {
                $binary = Storage::disk('local')->get($attachment['path']);
                if ($binary === null || $binary === '') {
                    throw new RuntimeException('Archivo vacío o no legible.');
                }

                $result = $this->client->sendDocument(
                    sessionId: $sessionId,
                    chatId: $chatId,
                    binaryContent: $binary,
                    filename: $attachment['filename'],
                    mimetype: $attachment['mimetype'],
                    caption: $isFirst ? $intro : null,
                );

                $isFirst = false;
                $messageIds[] = isset($result['messageId']) ? (string) $result['messageId'] : null;
                $sent++;

                usleep(600_000);
            } catch (Throwable $e) {
                Log::warning('Adjunto WhatsApp de laboratorio falló', [
                    'pedido_laboratorio_id' => $pedido->id,
                    'linea_id' => $attachment['linea_id'],
                    'filename' => $attachment['filename'],
                    'error' => $e->getMessage(),
                ]);

                throw new RuntimeException(
                    'No se pudo enviar '.$attachment['filename'].': '.$e->getMessage(),
                    0,
                    $e,
                );
            }
        }

        $this->recordSent(
            chatId: $chatId,
            recipientName: $recipientName,
            cuerpo: $intro."\n\n[adjuntos: ".implode(', ', array_column($attachments, 'filename')).']',
            pedidoId: $pedido->id,
            messageId: $messageIds[0] ?? null,
        );

        return ['sent' => $sent, 'message_ids' => $messageIds];
    }

    /**
     * @return list<array{linea_id: string, examen: string, path: string, filename: string, mimetype: string}>
     */
    private function resolveAttachments(PedidoLaboratorio $pedido): array
    {
        $attachments = [];

        /** @var PedidoLaboratorioLinea $linea */
        foreach ($pedido->lineas as $linea) {
            $path = trim((string) ($linea->resultado_archivo_path ?? ''));
            if ($path === '' || ! Storage::disk('local')->exists($path)) {
                continue;
            }

            $original = trim((string) ($linea->resultado_archivo_original_name ?? ''));
            $filename = $original !== ''
                ? $original
                : ('resultado-'.Str::slug($linea->nombre_examen, '-').'.'.(pathinfo($path, PATHINFO_EXTENSION) ?: 'pdf'));

            if ($filename === '' || $filename === '.') {
                $filename = 'resultado-examen.'.(pathinfo($path, PATHINFO_EXTENSION) ?: 'pdf');
            }

            $attachments[] = [
                'linea_id' => (string) $linea->id,
                'examen' => (string) $linea->nombre_examen,
                'path' => $path,
                'filename' => mb_substr($filename, 0, 255),
                'mimetype' => $this->mimeFromFilename($filename),
            ];
        }

        return $attachments;
    }

    private function mimeFromFilename(string $filename): string
    {
        $ext = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));

        return match ($ext) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'application/octet-stream',
        };
    }

    private function resolveReadySession(Tenant $tenant): ?TenantWhatsAppSession
    {
        $session = TenantWhatsAppSession::query()
            ->where('tenant_id', $tenant->id)
            ->first();

        if ($session === null) {
            $session = $this->sessionSync->ensureForTenant($tenant);
        } elseif (! $session->isReady()) {
            $session = $this->sessionSync->refresh($session);
        }

        return $session instanceof TenantWhatsAppSession && $session->isReady()
            ? $session
            : null;
    }

    private function recordSent(
        string $chatId,
        string $recipientName,
        string $cuerpo,
        string $pedidoId,
        ?string $messageId,
    ): void {
        try {
            NotificationQueue::query()->create([
                'tipo' => 'laboratorio_resultado',
                'canal' => NotificationQueue::CANAL_WHATSAPP,
                'destinatario' => $chatId,
                'destinatario_nombre' => $recipientName,
                'cuerpo' => $cuerpo,
                'referencia_tipo' => 'pedido_laboratorio',
                'referencia_id' => $pedidoId,
                'dedupe_key' => 'laboratorio_resultado:'.$pedidoId.':'.now()->timestamp,
                'enviar_at' => now(),
                'prioridad' => 3,
                'estado' => NotificationQueue::ESTADO_ENVIADO,
                'intentos' => 1,
                'max_intentos' => (int) config('openwa.max_attempts', 3),
                'ultimo_intento_at' => now(),
                'proveedor_msg_id' => $messageId,
            ]);
        } catch (Throwable) {
            // best-effort
        }
    }
}
