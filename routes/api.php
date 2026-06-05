<?php

use App\Http\Controllers\Api\Internal\SaasProvisionController;
use App\Http\Middleware\VerifyOrvaeProvisionSignature;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas API internas
|--------------------------------------------------------------------------
|
| /api/internal/* solo se debería exponer a llamadas server-to-server desde
| Orvae PE (firmadas con HMAC). NO usar para tráfico del frontend público.
|
*/

Route::prefix('internal/saas')
    ->middleware(VerifyOrvaeProvisionSignature::class)
    ->group(function (): void {
        Route::post('provision', [SaasProvisionController::class, 'provision'])
            ->name('api.internal.saas.provision');

        Route::post('renew', [SaasProvisionController::class, 'renew'])
            ->name('api.internal.saas.renew');

        Route::get('tenants/{slug}', [SaasProvisionController::class, 'status'])
            ->name('api.internal.saas.status');
    });
