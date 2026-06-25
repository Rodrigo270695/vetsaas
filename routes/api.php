<?php

use App\Http\Controllers\Api\Internal\SaasProvisionController;
use App\Http\Controllers\Api\SalesBotWebhookController;
use App\Http\Middleware\VerifyOrvaeProvisionSignature;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Bot de ventas IA — webhook de OpenWA
|--------------------------------------------------------------------------
|
| POST /api/webhooks/sales-bot
|
| OpenWA llama a este endpoint cuando llega un mensaje entrante al número
| de plataforma. El bot responde automáticamente con IA (OpenAI).
|
| Sin autenticación de sesión (es server-to-server), protegido por el
| header X-Webhook-Secret configurado en SALESBOT_WEBHOOK_SECRET.
|
| Configurar en wa-admin.vetsaas.orvae.pe:
|   Webhook URL → https://app.vetsaas.orvae.pe/api/webhooks/sales-bot
|   Header      → X-Webhook-Secret: <SALESBOT_WEBHOOK_SECRET>
|   Event       → onMessage
|
*/
Route::post('webhooks/sales-bot', [SalesBotWebhookController::class, 'handle'])
    ->name('api.webhooks.sales-bot');

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

        Route::get('tenants/{slug}/comprobantes-overage', [SaasProvisionController::class, 'comprobantesOverage'])
            ->name('api.internal.saas.comprobantes-overage');

        Route::get('tenants/{slug}', [SaasProvisionController::class, 'status'])
            ->name('api.internal.saas.status');

        Route::get('lookup', [SaasProvisionController::class, 'lookupByEmail'])
            ->name('api.internal.saas.lookup');
    });
