<?php

use App\Http\Controllers\Portal\PortalAuthController;
use App\Http\Controllers\Portal\PortalHomeController;
use App\Http\Controllers\Portal\PortalLandingController;
use App\Http\Controllers\Portal\PortalPushController;
use Illuminate\Support\Facades\Route;

/*
| Portal del propietario en subdominio del tenant o dominio personalizado.
| El flujo WhatsApp + invitación existente no cambia; el acceso por DNI
| (`/portal/acceso`) es opcional por clínica.
*/

Route::middleware(['portal.tenant-host', 'tenant.required'])->group(function (): void {
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

            Route::middleware(['portal.landing', 'throttle:20,1'])->group(function (): void {
                Route::get('acceso', [PortalLandingController::class, 'show'])
                    ->name('acceso');
                Route::post('acceso', [PortalLandingController::class, 'storeDocument'])
                    ->middleware('throttle:12,1')
                    ->name('acceso.document');
                Route::post('acceso/codigo', [PortalLandingController::class, 'storeOtp'])
                    ->middleware('throttle:20,1')
                    ->name('acceso.otp');
                Route::get('acceso/widget.js', [PortalLandingController::class, 'widget'])
                    ->name('acceso.widget');
            });

            Route::middleware('portal.auth')->group(function (): void {
                Route::get('/', [PortalHomeController::class, 'index'])->name('home');
                Route::post('push', [PortalPushController::class, 'store'])
                    ->name('push.store');
                Route::delete('push', [PortalPushController::class, 'destroy'])
                    ->name('push.destroy');
            });
        });
});