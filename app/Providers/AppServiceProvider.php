<?php

namespace App\Providers;

use App\Listeners\RecordUserLoginPresence;
use App\Models\User;
use App\Observers\AuditModelObserver;
use App\Support\Subscriptions\BotIaAccess;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
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
        $this->configureBotIaPermissionAliases();
        $this->registerAuditModelObservers();

        Event::listen(Login::class, RecordUserLoginPresence::class);
    }

  /**
   * Registra observadores de auditoría para modelos tenant configurados.
   */
    protected function registerAuditModelObservers(): void
    {
        $observer = AuditModelObserver::class;

        foreach (array_keys(config('audit.observed_models', [])) as $modelClass) {
            if (is_string($modelClass) && class_exists($modelClass)) {
                $modelClass::observe($observer);
            }
        }
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
     * Compatibilidad: `comunicaciones-bot-ia.*` se añadió después del deploy inicial.
     * Si el add-on está activo en la suscripción del tenant, quien administra la
     * clínica o las comunicaciones conserva acceso sin re-sincronizar roles.
     */
    protected function configureBotIaPermissionAliases(): void
    {
        Gate::before(function ($user, string $ability): ?bool {
            if (! $user instanceof User) {
                return null;
            }

            if (! in_array($ability, ['comunicaciones-bot-ia.view', 'comunicaciones-bot-ia.manage'], true)) {
                return null;
            }

            if ($user->hasPermissionTo($ability)) {
                return null;
            }

            if ($ability === 'comunicaciones-bot-ia.view' && BotIaAccess::userHasViewFallback($user)) {
                return true;
            }

            if (
                $ability === 'comunicaciones-bot-ia.view'
                && ! BotIaAccess::isActiveForCurrentTenant()
                && BotIaAccess::userHasComunicacionesAccess($user)
            ) {
                return true;
            }

            if (
                $ability === 'comunicaciones-bot-ia.manage'
                && BotIaAccess::isActiveForCurrentTenant()
                && BotIaAccess::userHasManageFallback($user)
            ) {
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
