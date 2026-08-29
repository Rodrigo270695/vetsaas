<?php

declare(strict_types=1);

namespace App\Services\Prospectos;

use App\Models\VeterinariaProspecto;
use App\Models\VeterinariaProspectoOutreachSetting;
use App\Services\OpenWa\PlatformWhatsAppMessenger;
use App\Services\Sales\SalesBotService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Orquesta el envío del PRIMER mensaje de contacto (IA + WhatsApp) hacia
 * `VeterinariaProspecto`, reutilizando toda la infraestructura del bot de
 * ventas (`SalesBotService` + `PlatformWhatsAppMessenger`) para que:
 *
 *  1. El mensaje salga redactado por IA, personalizado (nombre/ciudad).
 *  2. Quede enlazado a una `SalesConversation` — así, si el prospecto
 *     responde por WhatsApp, el webhook + chatbot IA que YA existe
 *     (`plataforma/salesbot-conversations`) sigue la charla automáticamente.
 *     No hace falta ninguna lógica nueva de "auto-respuesta".
 *
 * El envío MASIVO ("run") espacía cada mensaje con un delay aleatorio
 * (no configurable por el usuario) para simular un envío humano y evitar
 * bloqueos de WhatsApp. Está pensado para correr en background (Job en
 * cola o comando Artisan), nunca de forma síncrona en un request HTTP.
 */
final class VeterinariaProspectoOutreachService
{
    /** Nunca más de esta cantidad por corrida, sin importar la config guardada. */
    public const MAX_LIMIT = 20;

    /** Segundos mínimos de espera entre cada mensaje dentro de una corrida. */
    private const DELAY_BASE_SEG = 40;

    /** Jitter aleatorio adicional (0-N segundos) para no parecer un patrón robótico. */
    private const DELAY_JITTER_SEG = 20;

    public function __construct(
        private readonly SalesBotService $botService,
        private readonly PlatformWhatsAppMessenger $messenger,
    ) {}

    /**
     * Envía el mensaje de contacto a UN prospecto puntual (botón individual
     * del panel). Rápido (una sola llamada IA + WhatsApp): seguro de llamar
     * de forma síncrona desde un controller.
     */
    public function enviarIndividual(VeterinariaProspecto $prospecto, ?string $usuarioId = null): string
    {
        if (! $this->messenger->isReady()) {
            throw new RuntimeException('OpenWA (WhatsApp de plataforma) no está conectado.');
        }

        if ($prospecto->telefono_normalizado === null || $prospecto->telefono_normalizado === '') {
            throw new RuntimeException('Este prospecto no tiene un teléfono válido.');
        }

        $phone = $this->botService->normalizeLeadPhone($prospecto->telefono_normalizado);
        $waChatId = $phone.'@c.us';

        $conversation = $this->botService->findExistingConversation($phone, $waChatId)
            ?? $this->botService->createConversation(
                phone: $phone,
                waChatId: $waChatId,
                prospectName: $prospecto->nombre,
                trigger: $usuarioId !== null ? 'manual:prospecto-veterinaria' : 'cron:prospecto-veterinaria',
            );

        $ciudad = $prospecto->distrito ?? $prospecto->provincia ?? $prospecto->departamento;
        $tipoNegocio = $prospecto->tipo === VeterinariaProspecto::TIPO_HOSPITAL
            ? 'hospital veterinario'
            : 'clínica veterinaria';

        try {
            $mensaje = $this->botService->sendColdOutreachMessage(
                conversation: $conversation,
                prospectName: $prospecto->nombre,
                ciudad: $ciudad,
                tipoNegocio: $tipoNegocio,
            );
        } catch (Throwable $e) {
            $prospecto->mensaje_intentos = ($prospecto->mensaje_intentos ?? 0) + 1;
            $prospecto->mensaje_error = substr($e->getMessage(), 0, 290);
            $prospecto->save();

            throw $e;
        }

        $prospecto->sales_conversation_id = $conversation->id;
        $prospecto->mensaje_enviado_at = now();
        $prospecto->mensaje_enviado_por_id = $usuarioId;
        $prospecto->mensaje_intentos = ($prospecto->mensaje_intentos ?? 0) + 1;
        $prospecto->mensaje_error = null;
        $prospecto->estado = 'contactado';
        $prospecto->save();

        return $mensaje;
    }

    /**
     * Corrida masiva "global": toma hasta `$limit` prospectos elegibles
     * (sin filtro particular, cualquier departamento/tipo/etc.) y les
     * manda el mensaje de contacto uno por uno. Pensada para el cron
     * automático, que no tiene noción de "filtros aplicados en el panel".
     *
     * Para el envío manual desde el panel (que SÍ respeta los filtros que
     * el usuario tiene activos) usa `runForIds()`.
     *
     * IMPORTANTE: esta llamada puede tardar varios minutos (por el delay
     * anti-bloqueo). Debe ejecutarse en background (Job/comando), nunca
     * de forma síncrona en un controller.
     *
     * @return array{enviados: int, fallidos: int, sin_elegibles: bool}
     */
    public function run(int $limit, string $origen = 'automatico'): array
    {
        $limit = max(1, min($limit, self::MAX_LIMIT));

        $candidatos = VeterinariaProspecto::query()
            ->where('estado', 'nuevo')
            ->whereNull('mensaje_enviado_at')
            ->whereNotNull('telefono_normalizado')
            ->where('telefono_normalizado', '!=', '')
            ->orderBy('capturado_at')
            ->limit($limit)
            ->get();

        return $this->procesarCandidatos($candidatos, $origen);
    }

    /**
     * Corrida masiva sobre una lista EXACTA de IDs (ya resuelta por el
     * controller respetando los filtros que el usuario tenía aplicados en
     * el panel al presionar "Enviar ahora"). Vuelve a validar elegibilidad
     * por si algo cambió entre que se resolvió la lista y que corre el Job.
     *
     * @param  list<string>  $ids
     * @return array{enviados: int, fallidos: int, sin_elegibles: bool}
     */
    public function runForIds(array $ids, string $origen = 'manual'): array
    {
        if ($ids === []) {
            return ['enviados' => 0, 'fallidos' => 0, 'sin_elegibles' => true];
        }

        $candidatos = VeterinariaProspecto::query()
            ->whereIn('id', $ids)
            ->where('estado', 'nuevo')
            ->whereNull('mensaje_enviado_at')
            ->whereNotNull('telefono_normalizado')
            ->where('telefono_normalizado', '!=', '')
            ->orderBy('capturado_at')
            ->get();

        return $this->procesarCandidatos($candidatos, $origen);
    }

    /**
     * @param  Collection<int, VeterinariaProspecto>  $candidatos
     * @return array{enviados: int, fallidos: int, sin_elegibles: bool}
     */
    private function procesarCandidatos(Collection $candidatos, string $origen): array
    {
        if ($candidatos->isEmpty()) {
            return ['enviados' => 0, 'fallidos' => 0, 'sin_elegibles' => true];
        }

        if (! $this->messenger->isReady()) {
            Log::warning('ProspectosOutreach: OpenWA no conectado, corrida cancelada.', ['origen' => $origen]);

            throw new RuntimeException('OpenWA (WhatsApp de plataforma) no está conectado.');
        }

        $enviados = 0;
        $fallidos = 0;
        $total = $candidatos->count();
        $index = 0;

        foreach ($candidatos as $prospecto) {
            /** @var VeterinariaProspecto $prospecto */
            $index++;

            try {
                $this->enviarIndividual($prospecto, usuarioId: null);
                $enviados++;

                Log::info('ProspectosOutreach: mensaje enviado', [
                    'origen' => $origen,
                    'prospecto_id' => $prospecto->id,
                    'nombre' => $prospecto->nombre,
                ]);
            } catch (Throwable $e) {
                $fallidos++;

                Log::error('ProspectosOutreach: fallo al enviar', [
                    'origen' => $origen,
                    'prospecto_id' => $prospecto->id,
                    'error' => $e->getMessage(),
                ]);
            }

            if ($index < $total) {
                sleep(self::DELAY_BASE_SEG + random_int(0, self::DELAY_JITTER_SEG));
            }
        }

        VeterinariaProspectoOutreachSetting::current()->update(['ultima_corrida_at' => now()]);

        return ['enviados' => $enviados, 'fallidos' => $fallidos, 'sin_elegibles' => false];
    }
}
