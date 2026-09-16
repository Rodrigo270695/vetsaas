<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\Models\ClinicSetting;
use App\Models\Tenant;
use App\Models\TenantWhatsAppSession;
use App\Models\WhatsAppCampana;
use App\Models\WhatsAppCampanaDestinatario;
use App\Services\OpenWa\OpenWaClient;
use App\Services\OpenWa\OpenWaRateLimitedException;
use App\Services\OpenWa\TenantWhatsAppMessenger;
use App\Services\OpenWa\TenantWhatsAppSessionSync;
use App\Support\WhatsApp\WhatsAppCampaignMessageRenderer;
use App\Support\WhatsApp\WhatsAppChatId;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class WhatsAppCampaignDispatcher
{
    public function __construct(
        private readonly OpenWaClient $client,
        private readonly TenantWhatsAppSessionSync $sessionSync,
        private readonly TenantWhatsAppMessenger $messenger,
    ) {}

    /**
     * @return array{sent: int, skipped: int, failed: int}
     */
    public function tick(Tenant $tenant, ?CarbonInterface $now = null): array
    {
        $now ??= now();

        if (! Schema::hasTable('whatsapp_campanas')) {
            return ['sent' => 0, 'skipped' => 0, 'failed' => 0];
        }

        if (! $this->client->isConfigured() || $this->client->isRateLimited()) {
            return ['sent' => 0, 'skipped' => 1, 'failed' => 0];
        }

        $campana = WhatsAppCampana::query()
            ->where('estado', WhatsAppCampana::ESTADO_ENVIANDO)
            ->orderBy('started_at')
            ->first();

        if (! $campana instanceof WhatsAppCampana) {
            return ['sent' => 0, 'skipped' => 0, 'failed' => 0];
        }

        if (! $this->inWindow($campana, $now)) {
            return ['sent' => 0, 'skipped' => 1, 'failed' => 0];
        }

        $interval = max(8, min(30, (int) $campana->intervalo_minutos));
        if ($campana->last_sent_at instanceof CarbonInterface
            && $campana->last_sent_at->copy()->addMinutes($interval)->greaterThan($now)
        ) {
            return ['sent' => 0, 'skipped' => 1, 'failed' => 0];
        }

        $tope = max(50, min(100, (int) $campana->tope_diario));
        if ($campana->enviadosHoy() >= $tope) {
            return ['sent' => 0, 'skipped' => 1, 'failed' => 0];
        }

        $session = $this->sessionSync->ensureReadyForSend($tenant);
        if (! $session instanceof TenantWhatsAppSession || ! $session->isReady()) {
            return ['sent' => 0, 'skipped' => 1, 'failed' => 0];
        }

        $next = $campana->destinatarios()
            ->where('estado', WhatsAppCampanaDestinatario::ESTADO_PENDIENTE)
            ->orderBy('created_at')
            ->first();

        if (! $next instanceof WhatsAppCampanaDestinatario) {
            $campana->forceFill([
                'estado' => WhatsAppCampana::ESTADO_TERMINADA,
                'paused_at' => null,
            ])->save();

            return ['sent' => 0, 'skipped' => 0, 'failed' => 0];
        }

        return $this->sendOne($campana, $next, $session, $now);
    }

    /**
     * @return array{sent: int, skipped: int, failed: int}
     */
    private function sendOne(
        WhatsAppCampana $campana,
        WhatsAppCampanaDestinatario $destinatario,
        TenantWhatsAppSession $session,
        CarbonInterface $now,
    ): array {
        $variantes = $campana->variantesLimpias();
        if ($variantes === []) {
            return ['sent' => 0, 'skipped' => 1, 'failed' => 0];
        }

        $chatId = WhatsAppChatId::fromPhone($destinatario->telefono_normalizado);
        if ($chatId === null) {
            $destinatario->forceFill([
                'estado' => WhatsAppCampanaDestinatario::ESTADO_OMITIDO,
                'error' => 'Teléfono no válido para WhatsApp.',
            ])->save();

            return ['sent' => 0, 'skipped' => 1, 'failed' => 0];
        }

        $sentCount = $campana->destinatarios()
            ->where('estado', WhatsAppCampanaDestinatario::ESTADO_ENVIADO)
            ->count();
        $index = $sentCount % count($variantes);
        $template = $variantes[$index];
        $nombreCompleto = $destinatario->nombre_snapshot;
        $primerNombre = explode(' ', $nombreCompleto)[0] ?? $nombreCompleto;
        $clinica = trim((string) (
            ClinicSetting::query()->value('nombre_comercial')
            ?: ClinicSetting::query()->value('razon_social')
            ?: ''
        ));
        $mascota = (string) ($destinatario->mascota_nombres ?: 'tu mascota');

        $cuerpo = WhatsAppCampaignMessageRenderer::render($template, [
            'nombre' => $primerNombre,
            'nombre_completo' => $nombreCompleto,
            'propietario' => $nombreCompleto,
            'mascota' => $mascota,
            'clinica' => $clinica !== '' ? $clinica : 'la clínica',
        ]);

        if ($cuerpo === '') {
            $destinatario->forceFill([
                'estado' => WhatsAppCampanaDestinatario::ESTADO_OMITIDO,
                'error' => 'Mensaje vacío tras variables.',
            ])->save();

            return ['sent' => 0, 'skipped' => 1, 'failed' => 0];
        }

        try {
            $imageUrl = $campana->imagenUrl();
            if ($imageUrl !== null) {
                $this->messenger->sendImage(
                    $session,
                    $chatId,
                    $imageUrl,
                    mb_substr($cuerpo, 0, 1024),
                );
            } else {
                $this->messenger->sendText($session, $chatId, $cuerpo);
            }
        } catch (OpenWaRateLimitedException $e) {
            Log::warning('Campaña WhatsApp pausada por 429', [
                'campana_id' => $campana->id,
                'error' => $e->getMessage(),
            ]);

            return ['sent' => 0, 'skipped' => 1, 'failed' => 0];
        } catch (Throwable $e) {
            if ($this->client->isAmbiguousDeliveryError($e) || $this->client->isNoResponseTimeout($e)) {
                $this->markSent($campana, $destinatario, $index, $cuerpo, $now);

                return ['sent' => 1, 'skipped' => 0, 'failed' => 0];
            }

            $destinatario->forceFill([
                'estado' => WhatsAppCampanaDestinatario::ESTADO_FALLIDO,
                'error' => $e->getMessage(),
                'variante_index' => $index,
                'cuerpo_enviado' => $cuerpo,
            ])->save();

            $campana->forceFill(['last_sent_at' => $now])->save();

            return ['sent' => 0, 'skipped' => 0, 'failed' => 1];
        }

        $this->markSent($campana, $destinatario, $index, $cuerpo, $now);

        if (! $campana->destinatarios()->where('estado', WhatsAppCampanaDestinatario::ESTADO_PENDIENTE)->exists()) {
            $campana->forceFill([
                'estado' => WhatsAppCampana::ESTADO_TERMINADA,
                'paused_at' => null,
            ])->save();
        }

        return ['sent' => 1, 'skipped' => 0, 'failed' => 0];
    }

    private function markSent(
        WhatsAppCampana $campana,
        WhatsAppCampanaDestinatario $destinatario,
        int $index,
        string $cuerpo,
        CarbonInterface $now,
    ): void {
        $destinatario->forceFill([
            'estado' => WhatsAppCampanaDestinatario::ESTADO_ENVIADO,
            'error' => null,
            'variante_index' => $index,
            'cuerpo_enviado' => $cuerpo,
            'enviado_at' => $now,
        ])->save();

        $campana->forceFill(['last_sent_at' => $now])->save();
    }

    private function inWindow(WhatsAppCampana $campana, CarbonInterface $now): bool
    {
        $start = substr((string) $campana->hora_inicio, 0, 5);
        $end = substr((string) $campana->hora_fin, 0, 5);
        $hm = $now->format('H:i');

        return $hm >= $start && $hm <= $end;
    }
}
