<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Sales\SalesBotService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Fuerza al bot a intervenir en un chat de WhatsApp (crea conversación si no existe).
 *
 * Uso:
 *   php artisan salesbot:engage 51961777549
 *   php artisan salesbot:engage 51961777549 --message="Buenos días, información de costos"
 *   php artisan salesbot:engage 51961777549 --name="Beatriz Moscol" --dry-run
 */
final class SalesBotEngageCommand extends Command
{
    protected $signature = 'salesbot:engage
        {phone : Número sin + (ej: 51961777549 o 961777549)}
        {--message= : Mensaje del lead para contexto de la IA (default: saludo genérico)}
        {--name= : Nombre del prospecto si no está en la BD}
        {--dry-run : Genera la respuesta pero no envía por WhatsApp}';

    protected $description = 'Activa el bot y envía una respuesta IA a un lead de WhatsApp';

    public function __construct(
        private readonly SalesBotService $botService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $phone   = $this->botService->normalizeLeadPhone((string) $this->argument('phone'));
        $message = (string) ($this->option('message') ?: 'Hola, quisiera información sobre VetSaaS y los costos.');
        $name    = $this->option('name') !== null ? trim((string) $this->option('name')) : null;
        $dryRun  = (bool) $this->option('dry-run');

        if ($phone === '' || strlen($phone) < 8) {
            $this->error('Número inválido. Usa formato 51961777549 o 961777549.');

            return self::FAILURE;
        }

        $this->line("Contexto: \"{$message}\"");

        try {
            $result = $this->botService->engagePhone($phone, $message, $name, sendWhatsApp: ! $dryRun);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $conversation = $result['conversation'];

        $this->newLine();
        $this->comment('── Respuesta IA ──');
        $this->line($result['reply']);
        $this->newLine();

        if ($dryRun) {
            $this->warn('Dry-run: no se envió por WhatsApp.');
        } else {
            $this->info("✓ Mensaje enviado a {$phone} ({$conversation->prospect_name}).");
        }

        return self::SUCCESS;
    }
}
