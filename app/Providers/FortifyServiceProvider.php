<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Models\User;
use App\Services\Subscriptions\TenantSubscriptionAccess;
use App\Tenancy\TenantManager;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
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
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
        $this->configureAuthentication();
    }

    /**
     * Hook de autenticación.
     *
     * Desde Fase 2.6 el filtrado por `tenant_id` lo hace el provider
     * `tenant-eloquent` (ver {@see \App\Auth\TenantAwareEloquentUserProvider})
     * de forma transparente para todo el stack de auth (login, reset,
     * etc.). Aquí solo nos queda la validación específica del login:
     *
     *   - Email/password correctos (delegado al provider).
     *   - El usuario debe estar `is_active = true`. Una cuenta marcada
     *     inactiva no puede iniciar sesión aunque la contraseña sea
     *     correcta.
     *
     * Si retornamos `null`, Fortify responde con error de credenciales
     * inválidas (genérico, sin filtrar por motivo, para no revelar si
     * la cuenta existe o no).
     */
    private function configureAuthentication(): void
    {
        Fortify::authenticateUsing(function (Request $request) {
            $provider = Auth::createUserProvider('users');

            $credentials = [
                'email' => (string) $request->input('email'),
                'password' => (string) $request->input('password'),
            ];

            $user = $provider->retrieveByCredentials($credentials);

            if ($user === null || ! $provider->validateCredentials($user, $credentials)) {
                return null;
            }

            if ($user instanceof User && $user->is_active === false) {
                return null;
            }

            $tenant = app(TenantManager::class)->current()?->tenant;
            if ($tenant !== null) {
                $access = app(TenantSubscriptionAccess::class);
                $denial = $access->resolveDenial($tenant);

                if ($denial !== null) {
                    throw ValidationException::withMessages([
                        Fortify::username() => [$access->loginDeniedMessage($denial)],
                    ]);
                }
            }

            return $user;
        });
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'status' => $request->session()->get('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/reset-password', [
            'email' => $request->email,
            'token' => $request->route('token'),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/verify-email', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/two-factor-challenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirm-password'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}
