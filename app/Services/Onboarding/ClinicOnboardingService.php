<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Models\ClinicSetting;
use App\Models\Paciente;
use App\Models\Sede;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Checklist de configuración inicial para clínicas nuevas.
 *
 * Paso 0 (sede) es obligatorio: sin sede activa el middleware bloquea
 * módulos operativos. Los demás pasos son recomendados.
 */
class ClinicOnboardingService
{
    public const STEP_SEDE = 0;

    public const STEP_CLINIC = 1;

    public const STEP_PACIENTE = 2;

    public const TOTAL_STEPS = 3;

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

    /**
     * Panel visible en dashboard (incluye modo vista previa).
     */
    public function shouldShowCard(Tenant $tenant, ?Request $request = null): bool
    {
        if ($this->isPreviewMode($request)) {
            return true;
        }

        return $this->shouldShow($tenant);
    }

    public function isPreviewMode(?Request $request = null): bool
    {
        if ((bool) config('onboarding.preview', false)) {
            return true;
        }

        $request ??= request();

        return $request !== null && $request->boolean('onboarding_preview');
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

        $paso = self::STEP_PACIENTE;
        foreach ($flags as $index => $done) {
            if (! $done) {
                $paso = $index;
                break;
            }
        }

        $tenant->forceFill([
            'onboarding_paso' => $allComplete ? self::STEP_PACIENTE : $paso,
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
     *     preview: bool,
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
    public function snapshot(Tenant $tenant, User $user, ?Request $request = null): array
    {
        $preview = $this->isPreviewMode($request);

        if (! $preview) {
            $tenant = $this->sync($tenant);
        }

        $flags = $this->detectStepCompletion($tenant);
        $definitions = $this->stepDefinitions($user);
        $firstIncomplete = self::STEP_PACIENTE;

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
            'show' => $this->shouldShowCard($tenant, $request),
            'preview' => $preview,
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
            $this->hasPaciente(),
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
                'id' => 'paciente',
                'title' => 'steps.paciente.title',
                'description' => 'steps.paciente.description',
                'href' => $user->can('pacientes.view') ? '/clinica/pacientes' : null,
                'required' => false,
            ],
        ];
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

    private function hasPaciente(): bool
    {
        if (! Schema::hasTable('pacientes')) {
            return false;
        }

        return Paciente::query()
            ->whereNull('deleted_at')
            ->exists();
    }
}
