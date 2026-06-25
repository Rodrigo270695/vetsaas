<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SalesConversation;
use App\Services\Sales\SalesBotService;
use Illuminate\Console\Command;
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
        {--limit=30 : Número máximo de leads a procesar por corrida}';

    protected $description = 'Envía mensajes de reactivación a leads fríos que no convirtieron';

    public function __construct(
        private readonly SalesBotService $botService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun      = (bool) $this->option('dry-run');
        $inactiveDays = (int) $this->option('days');
        $limit        = (int) $this->option('limit');

        $this->info("🔍 Buscando leads fríos (inactivos +{$inactiveDays} días, máx {$limit})...");

        // ── Paso 0: cerrar automáticamente leads que agotaron sus 2 intentos ──
        // Si ya se enviaron 2 mensajes de reactivación y han pasado 3+ días sin
        // respuesta, se marcan como "perdidos" y no se vuelven a contactar.
        if (! $dryRun) {
            $exhausted = SalesConversation::query()
                ->where('converted', false)
                ->whereNull('lost_at')
                ->where('reactivation_count', '>=', 2)
                ->whereNotNull('last_reactivation_at')
                ->whereRaw("EXTRACT(EPOCH FROM (NOW() - last_reactivation_at))/86400 >= 3")
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
                    $this->line("  ⛔ [{$lead->phone}] " . ($lead->prospect_name ?? 'Sin nombre') . " — marcado como perdido");
                }
            }
        }

        // Consulta base: conversaciones que tuvieron actividad con el bot
        // pero llevan días sin escribir y aún no convirtieron ni se perdieron.
        $candidates = SalesConversation::query()
            ->where('converted', false)
            ->whereNull('lost_at')
            ->where('reactivation_count', '<', 2)
            // Elegible si tuvo al menos 1 turno con el bot O fue importado manualmente.
            ->where(function ($q): void {
                $q->where('turn_count', '>', 0)
                    ->orWhere('activation_trigger', 'like', 'manual:%');
            })
            ->where(function ($q) use ($inactiveDays): void {
                $q->whereNull('last_reactivation_at')
                    ->orWhereRaw('EXTRACT(EPOCH FROM (NOW() - last_reactivation_at))/86400 >= 3');
            })
            ->whereRaw("EXTRACT(EPOCH FROM (NOW() - COALESCE(last_message_at, created_at)))/86400 >= ?", [$inactiveDays])
            ->orderBy('last_message_at', 'asc')
            ->limit($limit)
            ->get();

        if ($candidates->isEmpty()) {
            $this->info('✅ No hay leads fríos para reactivar hoy.');
            return self::SUCCESS;
        }

        $this->info("📋 Leads encontrados: {$candidates->count()}");

        if ($dryRun) {
            $this->table(
                ['Phone', 'Nombre', 'Último mensaje', 'Intentos reactivación'],
                $candidates->map(fn ($c) => [
                    $c->phone,
                    $c->prospect_name ?? '(sin nombre)',
                    $c->last_message_at?->diffForHumans() ?? 'nunca',
                    $c->reactivation_count,
                ])->toArray(),
            );
            $this->warn('⚠️  Modo dry-run: no se envió ningún mensaje.');
            return self::SUCCESS;
        }

        $sent   = 0;
        $failed = 0;

        foreach ($candidates as $conversation) {
            /** @var SalesConversation $conversation */
            if (! $conversation->isEligibleForReactivation($inactiveDays)) {
                continue;
            }

            $name = $conversation->prospect_name ?? $conversation->phone;

            try {
                $message = $this->botService->sendReactivationMessage($conversation);

                $this->line(
                    "  ✅ [{$conversation->phone}] {$name} — intento #{$conversation->reactivation_count}"
                );

                Log::info('ReactivateColdLeads: mensaje enviado', [
                    'phone'   => $conversation->phone,
                    'name'    => $name,
                    'attempt' => $conversation->reactivation_count,
                    'message' => substr($message, 0, 80),
                ]);

                $sent++;

                // Pausa de 2 segundos entre envíos para no saturar OpenWA.
                sleep(2);

            } catch (\Throwable $e) {
                $this->error("  ❌ [{$conversation->phone}] {$name} — {$e->getMessage()}");

                Log::error('ReactivateColdLeads: fallo al enviar', [
                    'phone'   => $conversation->phone,
                    'error'   => $e->getMessage(),
                ]);

                $failed++;
            }
        }

        $this->newLine();
        $this->info("📊 Resumen: {$sent} enviados, {$failed} fallidos.");

        return self::SUCCESS;
    }
}
