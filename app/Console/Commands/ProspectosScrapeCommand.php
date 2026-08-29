<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Prospectos\VeterinariaProspectoScraperService;
use Illuminate\Console\Command;

/**
 * Trae diariamente entre ~30 y 50 clínicas/hospitales veterinarios nuevos
 * (sin duplicar) desde directorios públicos, para prospección comercial.
 */
class ProspectosScrapeCommand extends Command
{
    protected $signature = 'vetsaas:prospectos-scrape';

    protected $description = 'Scrapea clínicas/hospitales veterinarios de Perú (prospección comercial VetSaaS)';

    public function handle(VeterinariaProspectoScraperService $scraper): int
    {
        $result = $scraper->run(origen: 'cron');

        $this->info(sprintf(
            'Prospectos: %d nuevos, %d duplicados omitidos, %d ubicaciones sin datos (visitadas: %s)',
            $result['nuevos'],
            $result['duplicados'],
            $result['sin_datos'],
            implode(', ', $result['ubicaciones']),
        ));

        if ($result['errores'] !== []) {
            $this->warn('Errores: '.implode(' | ', $result['errores']));
        }

        return self::SUCCESS;
    }
}
