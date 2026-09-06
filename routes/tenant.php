<?php

use App\Http\Controllers\Auth\BootstrapLoginController;
use App\Http\Controllers\ConsultaHistoriaController;
use App\Http\Controllers\LaboratorioController;
use App\Http\Controllers\PacienteController;
use App\Http\Controllers\Portal\PortalAuthController;
use App\Http\Controllers\Portal\PortalHomeController;
use App\Http\Controllers\Portal\PortalPushController;
use App\Http\Controllers\PublicDocumentoAutorizacionController;
use App\Http\Controllers\Tenant\TenantDashboardController;
use App\Http\Controllers\VacunacionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas exclusivas del subdominio del tenant
|--------------------------------------------------------------------------
|
| Arquitectura "single-login + datos aislados": las rutas autenticadas
| (dashboard, módulos operativos, plataforma) son COMPARTIDAS con el
| panel central y viven en `routes/web.php`. El sidebar y la UI se
| filtran por permisos; el middleware `tenant.match-user` valida que el
| usuario pertenezca al host actual.
|
| Aquí solo queda la **landing pública** del subdominio: lo primero que
| ve un visitante anónimo (`clinica-x.localhost`) antes de hacer login.
|
| El parámetro `{tenant_subdomain}` se inyecta automáticamente desde el
| dominio (ver `bootstrap/app.php`). Es solo un comodín para que el
| routing matchee — la resolución real la hace `ResolveTenant`.
|
| Después de pasar por `tenant.required`, la conexión BD ya tiene el
| `search_path` apuntando al schema correcto.
|
*/

Route::middleware(['tenant.required'])->group(function (): void {
    Route::get('auth/bienvenida/{token}', [BootstrapLoginController::class, 'show'])
        ->where('token', '[A-Za-z0-9]{40,128}')
        ->middleware(['throttle:20,1'])
        ->name('tenant.auth.bootstrap');

    Route::post('auth/bienvenida/{token}', [BootstrapLoginController::class, 'store'])
        ->where('token', '[A-Za-z0-9]{40,128}')
        ->middleware(['throttle:20,1'])
        ->name('tenant.auth.bootstrap.store');

    Route::middleware(['signed', 'throttle:60,1'])
        ->prefix('documentos-publicos')
        ->name('tenant.public.clinical-history.')
        ->group(function (): void {
            Route::get('pacientes/{paciente}/historial', [PacienteController::class, 'publicHistorialView'])
                ->name('historial.view');
            Route::get('consultas/{consulta}.pdf', [ConsultaHistoriaController::class, 'publicPdf'])
                ->name('consulta');
            Route::get('pacientes/{paciente}/historial-clinico.pdf', [PacienteController::class, 'publicHistorialClinicoPdf'])
                ->name('historial');
            Route::get('vacunas/{vacuna_aplicada}/aplicacion.pdf', [VacunacionController::class, 'publicAplicacionPdf'])
                ->name('aplicacion');
            Route::get('laboratorio/lineas/{linea}/archivo', [LaboratorioController::class, 'publicDownloadResultadoArchivo'])
                ->name('laboratorio-archivo');
        });

    Route::middleware(['throttle:30,1'])
        ->prefix('documentos-publicos')
        ->group(function (): void {
            Route::get('autorizacion/{token}', [PublicDocumentoAutorizacionController::class, 'show'])
                ->where('token', '[A-Za-z0-9]{32,64}')
                ->name('tenant.public.autorizacion.show');
            Route::post('autorizacion/{token}', [PublicDocumentoAutorizacionController::class, 'store'])
                ->where('token', '[A-Za-z0-9]{32,64}')
                ->name('tenant.public.autorizacion.store');
        });

    Route::middleware(['throttle:40,1'])
        ->prefix('portal')
        ->name('tenant.portal.')
        ->group(function (): void {
            Route::get('sin-acceso', [PortalAuthController::class, 'sinAcceso'])
                ->name('sin-acceso');
            Route::post('salir', [PortalAuthController::class, 'logout'])
                ->name('logout');

            Route::get('entrar/{token}', [PortalAuthController::class, 'show'])
                ->where('token', '[a-f0-9]{64}')
                ->name('entrar');
            Route::post('entrar/{token}/pin', [PortalAuthController::class, 'storePin'])
                ->where('token', '[a-f0-9]{64}')
                ->name('pin.store');
            Route::post('entrar/{token}/desbloquear', [PortalAuthController::class, 'unlock'])
                ->where('token', '[a-f0-9]{64}')
                ->name('unlock');
            Route::post('entrar/{token}/reset', [PortalAuthController::class, 'sendReset'])
                ->where('token', '[a-f0-9]{64}')
                ->middleware('throttle:8,60')
                ->name('reset.send');
            Route::post('entrar/{token}/reset/confirmar', [PortalAuthController::class, 'confirmReset'])
                ->where('token', '[a-f0-9]{64}')
                ->name('reset.confirm');

            Route::get('pin', [PortalAuthController::class, 'showPin'])
                ->name('pin');
            Route::post('pin', [PortalAuthController::class, 'storePinIdentity'])
                ->name('pin.store.identity');
            Route::post('desbloquear', [PortalAuthController::class, 'unlockIdentity'])
                ->name('unlock.identity');
            Route::post('reset', [PortalAuthController::class, 'sendResetIdentity'])
                ->middleware('throttle:8,60')
                ->name('reset.send.identity');
            Route::post('reset/confirmar', [PortalAuthController::class, 'confirmResetIdentity'])
                ->name('reset.confirm.identity');

            Route::middleware('portal.auth')->group(function (): void {
                Route::get('/', [PortalHomeController::class, 'index'])->name('home');
                Route::post('push', [PortalPushController::class, 'store'])
                    ->name('push.store');
                Route::delete('push', [PortalPushController::class, 'destroy'])
                    ->name('push.destroy');
            });
        });

    Route::get('/', [TenantDashboardController::class, 'welcome'])
        ->name('tenant.home');
});
