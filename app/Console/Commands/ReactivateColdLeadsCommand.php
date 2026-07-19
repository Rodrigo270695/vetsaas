<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SalesConversation;
use App\Services\Sales\SalesBotService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

/**
 * Envía mensajes de reactivación a leads fríos de VetSaaS.
 *
 * Un lead frío es alguien que interactuó con el bot de ventas
 * pero no ha vuelto a escribir en varios días y no se convirtió.
 *
 * El comando se ejecuta diariamente y:
 *  1. Busca conversaciones que califiquen para reactivación.
 *  2. Genera un mensaje personalizado con IA.
 *  3. Lo envía por WhatsApp vía OpenWA.
 *  4. Registra el intento en la BD.
 *
 * Máximo 2 intentos por lead, con al menos 3 días entre intentos.
 *
 * Uso:
 *   php artisan vetsaas:reactivate-cold-leads
 *   php artisan vetsaas:reactivate-cold-leads --dry-run   (solo lista, no envía)
 *   php artisan vetsaas:reactivate-cold-leads --days=5    (inactivos +5 días)
 *   php artisan vetsaas:reactivate-cold-leads --limit=20  (máximo 20 por corrida)
 */
final class ReactivateColdLeadsCommand extends Command
{
    protected $signature = 'vetsaas:reactivate-cold-leads
        {--dry-run : Solo muestra los leads que se reactivarían, sin enviar mensajes}
        {--days=3  : Días de inactividad mínimo para considerar el lead como frío}
        {--limit=15 : Número máximo de leads a procesar por corrida (máx recomendado: 20/día)}
        {--delay=15 : Segundos de espera entre mensajes (mínimo recomendado: 10s)}';

    protected $description = 'Envía mensajes de reactivación a leads fríos que no convirtieron';

    public function __construct(
        private readonly SalesBotService $botService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $inactiveDays = (int) $this->option('days');
        $limit = min((int) $this->option('limit'), 20); // nunca más de 20 por corrida
        $delay = max((int) $this->option('delay'), 10); // nunca menos de 10s entre mensajes

        $this->info("🔍 Buscando leads fríos (inactivos +{$inactiveDays} días, máx {$limit})...");

        // ── Paso 0: cerrar automáticamente leads que agotaron sus 2 intentos ──
        if (! $dryRun) {
            $exhausted = SalesConversation::query()
                ->where('converted', false)
                ->whereNull('lost_at')
                ->where('reactivation_count', '>=', 2)
                ->whereNotNull('last_reactivation_at')
                ->whereRaw('EXTRACT(EPOCH FROM (NOW() - last_reactivation_at))/86400 >= 3')
                ->where(function ($q): void {
                    $q->where('turn_count', '>', 0)
                        ->orWhere('activation_trigger', 'like', 'manual:%');
                })
                ->get();

            if ($exhausted->isNotEmpty()) {
                $this->warn("🔒 Cerrando {$exhausted->count()} leads sin respuesta tras 2 intentos...");
                foreach ($exhausted as $lead) {
                    /** @var SalesConversation $lead */
                    $lead->markLost();
                    $this->line('  ⛔ ['.$lead->phone.'] '.($lead->prospect_name ?? 'Sin nombre').' — marcado como perdido');
                }
            }
        }

        // Sobre-muestrear y filtrar en PHP para no llenar el cupo con pausados
        // (antes: limit=N tomaba los más viejos pausados → 0 envíos silenciosos).
        $pool = $this->coldLeadsQuery($inactiveDays)
            ->orderBy('last_message_at')
            ->limit(max($limit * 5, 50))
            ->get();

        $candidates = $pool
            ->filter(fn (SalesConversation $c): bool => $c->isEligibleForReactivation($inactiveDays))
            ->take($limit)
            ->values();

        if ($candidates->isEmpty()) {
            $pausedInPool = $pool->filter(fn (SalesConversation $c): bool => $c->isManuallyPaused())->count();
            $this->info('✅ No hay leads fríos elegibles para reactivar hoy.');
            if ($pausedInPool > 0) {
                $this->warn("   ({$pausedInPool} en el pool están pausados manualmente y se omitieron)");
            }

            return self::SUCCESS;
        }

        $this->info("📋 Leads elegibles: {$candidates->count()}");

        if ($dryRun) {
            $this->table(
                ['Phone', 'Nombre', 'Último mensaje', 'Intentos reactivación', 'Pausado'],
                $candidates->map(fn (SalesConversation $c): array => [
                    $c->phone,
                    $c->prospect_name ?? '(sin nombre)',
                    $c->last_message_at?->diffForHumans() ?? 'nunca',
                    $c->reactivation_count,
                    $c->isManuallyPaused() ? 'sí' : 'no',
                ])->toArray(),
            );
            $this->warn('⚠️  Modo dry-run: no se envió ningún mensaje.');

            return self::SUCCESS;
        }

        $sent = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($candidates as $conversation) {
            /** @var SalesConversation $conversation */
            if (! $conversation->isEligibleForReactivation($inactiveDays)) {
                $reason = $conversation->isManuallyPaused() ? 'pausado manual' : 'no elegible';
                $this->line("  ⏭️  [{$conversation->phone}] omitido ({$reason})");
                $skipped++;

                continue;
            }

            $name = $conversation->prospect_name ?? $conversation->phone;

            try {
                $message = $this->botService->sendReactivationMessage($conversation);

                $this->line(
                    "  ✅ [{$conversation->phone}] {$name} — intento #{$conversation->reactivation_count}"
                );

                Log::info('ReactivateColdLeads: mensaje enviado', [
                    'phone' => $conversation->phone,
                    'name' => $name,
                    'attempt' => $conversation->reactivation_count,
                    'message' => substr($message, 0, 80),
                ]);

                $sent++;

                $sleepSecs = $delay + rand(0, 8);
                $this->line("    ⏳ Esperando {$sleepSecs}s antes del próximo envío...");
                sleep($sleepSecs);
            } catch (\Throwable $e) {
                $this->error("  ❌ [{$conversation->phone}] {$name} — {$e->getMessage()}");

                Log::error('ReactivateColdLeads: fallo al enviar', [
                    'phone' => $conversation->phone,
                    'error' => $e->getMessage(),
                ]);

                // Evita que un número con OpenWA 500 bloquee la cola todos los días.
                // Rota 3 días sin gastar el cupo de reactivación.
                $conversation->last_reactivation_at = now();
                $conversation->save();

                $failed++;
            }
        }

        $this->newLine();
        $this->info("📊 Resumen: {$sent} enviados, {$failed} fallidos, {$skipped} omitidos.");

        return self::SUCCESS;
    }

    /**
     * @return Builder<SalesConversation>
     */
    private function coldLeadsQuery(int $inactiveDays): Builder
    {
        return SalesConversation::query()
            ->where('converted', false)
            ->whereNull('lost_at')
            ->where('reactivation_count', '<', 2)
            ->where(function ($q): void {
                $q->where('bot_paused_manually', false)
                    ->orWhereNull('bot_paused_manually');
            })
            ->where(function ($q): void {
                $q->where('turn_count', '>', 0)
                    ->orWhere('activation_trigger', 'like', 'manual:%');
            })
            ->where(function ($q): void {
                $q->whereNull('last_reactivation_at')
                    ->orWhereRaw('EXTRACT(EPOCH FROM (NOW() - last_reactivation_at))/86400 >= 3');
            })
            ->whereRaw(
                'EXTRACT(EPOCH FROM (NOW() - COALESCE(last_message_at, created_at)))/86400 >= ?',
                [$inactiveDays],
            );
    }
}
