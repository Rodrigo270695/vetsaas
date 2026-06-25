<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SalesConversation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Importa leads fríos desde un archivo CSV.
 *
 * Uso:
 *   php artisan vetsaas:import-leads --template          Descarga la plantilla
 *   php artisan vetsaas:import-leads leads.csv           Importa el archivo
 *   php artisan vetsaas:import-leads leads.csv --days=6  Marca inactivos 6 días atrás
 *   php artisan vetsaas:import-leads leads.csv --dry-run Solo muestra qué importaría
 *
 * Formato del CSV:
 *   phone,name,note
 *   51987654321,José Rosales,Preguntó por precio Starter
 *   51993897841,,
 *
 * Reglas:
 *   - phone: solo dígitos, con código de país (51 para Perú). Obligatorio.
 *   - name: opcional. Si está vacío se deja como "Sin nombre".
 *   - note: opcional. Se guarda como primer mensaje de contexto.
 *   - Duplicados (mismo phone) son ignorados silenciosamente.
 */
final class ImportLeadsCommand extends Command
{
    protected $signature = 'vetsaas:import-leads
        {file? : Ruta al archivo CSV (absoluta o relativa al directorio raíz)}
        {--template : Genera el archivo plantilla leads_template.csv en storage/app}
        {--days=5   : Días de inactividad simulada (para que el scheduler los tome como fríos)}
        {--dry-run  : Solo muestra qué se importaría, sin guardar nada}';

    protected $description = 'Importa leads fríos desde CSV. Usa --template para descargar la plantilla.';

    public function handle(): int
    {
        if ($this->option('template')) {
            return $this->generateTemplate();
        }

        $file = (string) ($this->argument('file') ?? '');
        if ($file === '') {
            $this->error('Especifica el archivo CSV o usa --template para generar la plantilla.');
            $this->line('  Ejemplo: php artisan vetsaas:import-leads leads.csv');
            return self::FAILURE;
        }

        // Resolver ruta: absoluta o relativa al base_path.
        $path = file_exists($file) ? $file : base_path($file);
        if (! file_exists($path)) {
            $this->error("Archivo no encontrado: {$path}");
            return self::FAILURE;
        }

        return $this->importFile($path);
    }

    private function generateTemplate(): int
    {
        $csv = implode("\n", [
            'phone,name,note',
            '51987654321,José Rosales,Preguntó por precio del plan Starter',
            '51993897841,Ana Torres,',
            '51961343351,,Mandó mensaje de voz preguntando por VetSaaS',
        ]);

        $storagePath = storage_path('app/leads_template.csv');
        file_put_contents($storagePath, $csv);

        $this->info("✅ Plantilla generada en: {$storagePath}");
        $this->line('');
        $this->line('Columnas del CSV:');
        $this->line('  phone  → número con código de país, solo dígitos (ej: 51987654321). Obligatorio.');
        $this->line('  name   → nombre del prospecto. Opcional.');
        $this->line('  note   → nota o contexto. Opcional. Se guarda como primer mensaje del historial.');
        $this->line('');
        $this->line('Para importar el archivo ya completo:');
        $this->line('  php artisan vetsaas:import-leads '.$storagePath);

        return self::SUCCESS;
    }

    private function importFile(string $path): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $days   = (int) $this->option('days');

        $handle = fopen($path, 'r');
        if ($handle === false) {
            $this->error("No se puede leer el archivo: {$path}");
            return self::FAILURE;
        }

        $headers = null;
        $imported = 0;
        $skipped  = 0;
        $errors   = 0;
        $rows     = [];

        while (($row = fgetcsv($handle)) !== false) {
            if ($headers === null) {
                // Primera línea: cabeceras.
                $headers = array_map('strtolower', array_map('trim', $row));
                continue;
            }

            if (count($row) === 0 || (count($row) === 1 && trim($row[0]) === '')) {
                continue;
            }

            $data = [];
            foreach ($headers as $i => $header) {
                $data[$header] = trim($row[$i] ?? '');
            }

            $rows[] = $data;
        }

        fclose($handle);

        $this->info("📋 {$path} — " . count($rows) . " filas encontradas" . ($dryRun ? ' (dry-run)' : '') . ".");
        $this->line('');

        foreach ($rows as $data) {
            $phone = preg_replace('/\D/', '', $data['phone'] ?? '');

            if ($phone === '' || strlen($phone) < 8) {
                $this->warn("  ⚠️  Teléfono inválido: '{$data['phone']}' — fila ignorada");
                $errors++;
                continue;
            }

            $name = ($data['name'] ?? '') !== '' ? $data['name'] : null;
            $note = ($data['note'] ?? '') !== '' ? $data['note'] : null;

            // Verificar duplicado.
            $exists = SalesConversation::query()->where('phone', $phone)->exists();
            if ($exists) {
                $this->line("  ⏭️  [{$phone}] " . ($name ?? 'Sin nombre') . " — ya existe, omitido");
                $skipped++;
                continue;
            }

            $this->line("  " . ($dryRun ? '👁️ ' : '✅ ') . " [{$phone}] " . ($name ?? 'Sin nombre') . ($note ? " — {$note}" : ''));

            if (! $dryRun) {
                $messages = [];
                if ($note !== null) {
                    // Guardamos la nota como primer mensaje del prospecto para dar contexto al bot.
                    $messages[] = ['role' => 'user', 'content' => $note];
                }

                SalesConversation::query()->create([
                    'phone'              => $phone,
                    'wa_chat_id'         => $phone . '@c.us',
                    'prospect_name'      => $name,
                    'messages'           => $messages,
                    'turn_count'         => count($messages),
                    'bot_active'         => false,
                    'activation_trigger' => 'manual:csv-import',
                    'last_message_at'    => now()->subDays($days),
                    'reactivation_count' => 0,
                    'converted'          => false,
                ]);
            }

            $imported++;
        }

        $this->line('');
        $this->info("📊 Resumen: {$imported} importados, {$skipped} duplicados omitidos, {$errors} errores.");

        if (! $dryRun && $imported > 0) {
            $this->line('');
            $this->line("Los {$imported} leads se marcaron con {$days} días de inactividad.");
            $this->line("El scheduler de las 10:00 AM los tomará como fríos la próxima corrida.");
            $this->line("O puedes reactivarlos ahora mismo:");
            $this->line("  php artisan vetsaas:reactivate-cold-leads");
        }

        return self::SUCCESS;
    }
}
