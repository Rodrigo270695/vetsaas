<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\VeterinariaProspectoOutreachSetting;
use App\Services\Prospectos\VeterinariaProspectoOutreachService;
use Illuminate\Console\Command;

/**
 * Corrida automática diaria de mensajes de contacto (IA + WhatsApp) hacia
 * prospectos veterinarios nuevos.
 *
 * El scheduler llama a este comando cada hora (`->hourly()`), pero solo
 * hace algo si:
 *   1. El envío automático está activado desde el panel
 *      (Prospectos veterinarias → Configurar envío IA).
 *   2. La hora actual (America/Lima) coincide con la hora configurada.
 *
 * Este patrón de "revisar cada hora si toca" permite que la hora de envío
 * sea configurable desde el admin sin tener que re-registrar el scheduler
 * en cada deploy.
 *
 * Uso:
 *   php artisan vetsaas:prospectos-outreach            (respeta config/hora)
 *   php artisan vetsaas:prospectos-outreach --force    (ignora activo/hora, útil para probar)
 *   php artisan vetsaas:prospectos-outreach --force --limit=3
 */
final class ProspectosOutreachCommand extends Command
{
    protected $signature = 'vetsaas:prospectos-outreach
        {--force : Ignora el check de "activo" y de hora (para pruebas manuales)}
        {--limit= : Sobrescribe la cantidad configurada de mensajes a enviar}';

    protected $description = 'Envía el primer mensaje de contacto (IA + WhatsApp) a prospectos veterinarios nuevos';

    public function handle(VeterinariaProspectoOutreachService $service): int
    {
        $setting = VeterinariaProspectoOutreachSetting::current();
        $force = (bool) $this->option('force');

        if (! $force && ! $setting->automatico_activo) {
            $this->info('El envío automático está desactivado. Nada que hacer.');

            return self::SUCCESS;
        }

        if (! $force && ! $setting->esHoraDeCorrida()) {
            $this->line("No es la hora configurada ({$setting->hora_envio}). Nada que hacer.");

            return self::SUCCESS;
        }

        $limitOption = $this->option('limit');
        $limit = $limitOption !== null ? (int) $limitOption : $setting->mensajes_por_corrida;

        $this->info("Lanzando corrida de outreach (máx {$limit} mensajes)...");

        try {
            $resultado = $service->run($limit, origen: 'automatico');
        } catch (\Throwable $e) {
            $this->error('No se pudo lanzar la corrida: '.$e->getMessage());

            return self::FAILURE;
        }

        $setting->ultima_corrida_at = now();
        $setting->save();

        if ($resultado['sin_elegibles']) {
            $this->info('No había prospectos elegibles (nuevos, con teléfono, sin contactar).');

            return self::SUCCESS;
        }

        $this->info("Resumen: {$resultado['enviados']} enviados, {$resultado['fallidos']} fallidos.");

        return self::SUCCESS;
    }
}
