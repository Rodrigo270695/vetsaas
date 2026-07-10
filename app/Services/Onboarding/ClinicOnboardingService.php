<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Models\Cita;
use App\Models\ClinicSetting;
use App\Models\FelSerie;
use App\Models\Paciente;
use App\Models\Sede;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Support\Facades\Schema;

/**
 * Checklist de configuración inicial para clínicas nuevas.
 *
 * Paso 0 (sede) es obligatorio: sin sede activa el middleware bloquea
 * módulos operativos. Los demás pasos son recomendados y se detectan
 * automáticamente al cargar el dashboard.
 */
class ClinicOnboardingService
{
    public const STEP_SEDE = 0;

    public const STEP_CLINIC = 1;

    public const STEP_TEAM = 2;

    public const STEP_PACIENTE = 3;

    public const STEP_ACTIVITY = 4;

    public const STEP_FEL = 5;

    public const TOTAL_STEPS = 6;

    public function isActiveForTenant(Tenant $tenant): bool
    {
        $slugs = config('onboarding.enabled_slugs', []);

        if ($slugs !== []) {
            return in_array(strtolower((string) $tenant->slug), $slugs, true);
        }

        return (bool) config('onboarding.enabled', false);
    }

    public function shouldShow(Tenant $tenant): bool
    {
        return $this->isActiveForTenant($tenant) && ! $tenant->onboarding_completado;
    }

    public function hasActiveSede(string $tenantId): bool
    {
        return Sede::query()
            ->where('tenant_id', $tenantId)
            ->where('activa', true)
            ->whereNull('deleted_at')
            ->exists();
    }

    /**
     * Sincroniza `onboarding_paso` y `onboarding_completado` según el estado real.
     */
    public function sync(Tenant $tenant): Tenant
    {
        if (! $this->isActiveForTenant($tenant)) {
            return $tenant;
        }

        $flags = $this->detectStepCompletion($tenant);
        $allComplete = ! in_array(false, $flags, true);

        $paso = self::STEP_FEL;
        foreach ($flags as $index => $done) {
            if (! $done) {
                $paso = $index;
                break;
            }
        }

        $tenant->forceFill([
            'onboarding_paso' => $allComplete ? self::STEP_FEL : $paso,
            'onboarding_completado' => $allComplete,
        ]);

        if ($tenant->isDirty(['onboarding_paso', 'onboarding_completado'])) {
            $tenant->save();
        }

        return $tenant->refresh();
    }

    /**
     * @return array{
     *     show: bool,
     *     completed: bool,
     *     paso: int,
     *     total_steps: int,
     *     completed_steps: int,
     *     requires_sede: bool,
     *     steps: list<array{
     *         id: string,
     *         title: string,
     *         description: string,
     *         href: ?string,
     *         completed: bool,
     *         current: bool,
     *         locked: bool,
     *         required: bool
     *     }>
     * }
     */
    public function snapshot(Tenant $tenant, User $user): array
    {
        $tenant = $this->sync($tenant);
        $flags = $this->detectStepCompletion($tenant);
        $definitions = $this->stepDefinitions($user);
        $firstIncomplete = self::STEP_FEL;

        foreach ($flags as $index => $done) {
            if (! $done) {
                $firstIncomplete = $index;
                break;
            }
        }

        $steps = [];
        foreach ($definitions as $index => $definition) {
            $completed = $flags[$index] ?? false;
            $locked = $index > 0 && ! ($flags[0] ?? false);
            $steps[] = [
                'id' => $definition['id'],
                'title' => $definition['title'],
                'description' => $definition['description'],
                'href' => $locked || $definition['href'] === null ? null : $definition['href'],
                'completed' => $completed,
                'current' => ! $tenant->onboarding_completado && $index === $firstIncomplete,
                'locked' => $locked,
                'required' => $definition['required'],
            ];
        }

        $completedSteps = count(array_filter($flags));

        return [
            'show' => $this->shouldShow($tenant),
            'completed' => (bool) $tenant->onboarding_completado,
            'paso' => (int) $tenant->onboarding_paso,
            'total_steps' => self::TOTAL_STEPS,
            'completed_steps' => $completedSteps,
            'requires_sede' => ! ($flags[self::STEP_SEDE] ?? false),
            'steps' => $steps,
        ];
    }

    /**
     * @return list<bool>
     */
    private function detectStepCompletion(Tenant $tenant): array
    {
        $tenantId = (string) $tenant->id;

        return [
            $this->hasActiveSede($tenantId),
            $this->hasClinicProfile(),
            $this->hasTeam($tenantId),
            $this->hasPaciente(),
            $this->hasFirstActivity(),
            $this->hasFelConfigured(),
        ];
    }

    /**
     * @return list<array{id: string, title: string, description: string, href: ?string, required: bool}>
     */
    private function stepDefinitions(User $user): array
    {
        return [
            [
                'id' => 'sede',
                'title' => 'steps.sede.title',
                'description' => 'steps.sede.description',
                'href' => $user->can('sedes.view') ? '/configuracion/sedes' : null,
                'required' => true,
            ],
            [
                'id' => 'clinic',
                'title' => 'steps.clinic.title',
                'description' => 'steps.clinic.description',
                'href' => $user->can('config-general.view') ? '/configuracion/general' : null,
                'required' => false,
            ],
            [
                'id' => 'team',
                'title' => 'steps.team.title',
                'description' => 'steps.team.description',
                'href' => $user->can('usuarios.view') ? '/configuracion/usuarios' : null,
                'required' => false,
            ],
            [
                'id' => 'paciente',
                'title' => 'steps.paciente.title',
                'description' => 'steps.paciente.description',
                'href' => $user->can('pacientes.view') ? '/clinica/pacientes' : null,
                'required' => false,
            ],
            [
                'id' => 'activity',
                'title' => 'steps.activity.title',
                'description' => 'steps.activity.description',
                'href' => $this->resolveActivityHref($user),
                'required' => false,
            ],
            [
                'id' => 'fel',
                'title' => 'steps.fel.title',
                'description' => 'steps.fel.description',
                'href' => $user->can('config-general.view') ? '/configuracion/general' : null,
                'required' => false,
            ],
        ];
    }

    private function resolveActivityHref(User $user): ?string
    {
        if ($user->can('citas.view')) {
            return '/clinica/citas';
        }

        if ($user->can('ventas.create')) {
            return '/caja/ventas/create';
        }

        return null;
    }

    private function hasClinicProfile(): bool
    {
        if (! Schema::hasTable('cfg_clinic_settings')) {
            return false;
        }

        $clinic = ClinicSetting::query()->first();

        if ($clinic === null) {
            return false;
        }

        $ruc = trim((string) ($clinic->ruc ?? ''));
        $razon = trim((string) ($clinic->razon_social ?? ''));
        $comercial = trim((string) ($clinic->nombre_comercial ?? ''));

        return $ruc !== '' && ($razon !== '' || $comercial !== '');
    }

    private function hasTeam(string $tenantId): bool
    {
        return User::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->count() >= 2;
    }

    private function hasPaciente(): bool
    {
        if (! Schema::hasTable('pacientes')) {
            return false;
        }

        return Paciente::query()
            ->whereNull('deleted_at')
            ->exists();
    }

    private function hasFirstActivity(): bool
    {
        $hasCita = Schema::hasTable('citas')
            && Cita::query()->whereNull('deleted_at')->exists();

        if ($hasCita) {
            return true;
        }

        if (! Schema::hasTable('ventas')) {
            return false;
        }

        return Venta::query()
            ->where('estado', Venta::ESTADO_PAGADO)
            ->whereNull('deleted_at')
            ->exists();
    }

    private function hasFelConfigured(): bool
    {
        if (! Schema::hasTable('cfg_clinic_settings')) {
            return false;
        }

        $clinic = ClinicSetting::query()->first();

        if ($clinic !== null && (bool) ($clinic->apisunat_configurado ?? false)) {
            return true;
        }

        if (! Schema::hasTable('fel_series')) {
            return false;
        }

        return FelSerie::query()->where('activo', true)->exists();
    }
}
