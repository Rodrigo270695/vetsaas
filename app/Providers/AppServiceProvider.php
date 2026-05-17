<?php

namespace App\Providers;

use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureHistoriasClinicasPlanesPermissionAliases();
    }

    /**
     * Compatibilidad: los permisos `historias-clinicas-planes.*` se añadieron después
     * de `historias-clinicas.*`. Quien ya podía ver/editar consultas conserva acceso
     * al plan y al seguimiento sin tener que re-sincronizar roles manualmente.
     */
    protected function configureHistoriasClinicasPlanesPermissionAliases(): void
    {
        Gate::before(function ($user, string $ability): ?bool {
            if (! $user instanceof User) {
                return null;
            }

            if ($ability === 'historias-clinicas-planes.view' && $user->can('historias-clinicas.view')) {
                return true;
            }

            if ($ability === 'historias-clinicas-planes.manage'
                && ($user->can('historias-clinicas.update') || $user->can('historias-clinicas.create'))) {
                return true;
            }

            return null;
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        $locale = config('app.locale', 'es');
        Carbon::setLocale($locale);
        CarbonImmutable::setLocale($locale);

        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
