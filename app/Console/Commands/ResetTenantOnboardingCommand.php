<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;

class ResetTenantOnboardingCommand extends Command
{
    protected $signature = 'vetsaas:onboarding-reset {slug : Slug del tenant (subdominio)}';

    protected $description = 'Reinicia el wizard de onboarding de un tenant para pruebas';

    public function handle(): int
    {
        $slug = strtolower(trim((string) $this->argument('slug')));

        $tenant = Tenant::query()->where('slug', $slug)->first();

        if ($tenant === null) {
            $this->error("No se encontró el tenant con slug [{$slug}].");

            return self::FAILURE;
        }

        $tenant->forceFill([
            'onboarding_completado' => false,
            'onboarding_paso' => 0,
        ])->save();

        $this->info("Onboarding reiniciado para [{$slug}].");
        $this->line('Asegúrate de tener ONBOARDING_ENABLED_SLUGS='.$slug.' (o ONBOARDING_ENABLED=true) en .env');

        return self::SUCCESS;
    }
}
