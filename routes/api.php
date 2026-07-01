<?php

use App\Http\Controllers\Api\ClinicBotWebhookController;
use App\Http\Controllers\Api\Internal\SaasProvisionController;
use App\Http\Controllers\Api\Public\TenantShowcaseController;
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
| Asistente IA de clínica — webhook OpenWA (sesiones tenant)
|--------------------------------------------------------------------------
|
| POST /api/webhooks/clinic-bot
|
| Misma URL para todas las clínicas; el tenant se resuelve por sessionId.
| Header: X-Webhook-Secret = BOT_IA_WEBHOOK_SECRET
|
*/
Route::post('webhooks/clinic-bot', [ClinicBotWebhookController::class, 'handle'])
    ->name('api.webhooks.clinic-bot');

/*
|--------------------------------------------------------------------------
| Showcase público — carrusel de clientes VetSaaS (Orvae marketing)
|--------------------------------------------------------------------------
|
| GET /api/public/vetsaas/showcase
|
| Solo clínicas con plan de pago (no free) y logo propio subido.
| Cache 15 min. Rate limit 60 req/min por IP.
|
*/
Route::get('public/vetsaas/showcase', [TenantShowcaseController::class, 'index'])
    ->middleware('throttle:60,1')
    ->name('api.public.vetsaas.showcase');

/*
| TODO — Rutas futuras por producto (Opción A):
|
| Cuando Orvae agregue más SaaS al bot, registrar una ruta por producto.
| Cada ruta tiene su propio webhook en OpenWA (sesión o webhook separado).
| El controlador recibe el slug del producto y carga el system prompt correcto.
|
|   Route::post('webhooks/sales-bot/aula-virtual', ...)
|       ->name('api.webhooks.sales-bot.aula-virtual');
|
|   Route::post('webhooks/sales-bot/inventario', ...)
|       ->name('api.webhooks.sales-bot.inventario');
|
| En OpenWA: registrar un webhook por ruta con el mismo secret.
| En SalesBotService: agregar buildSystemPromptForProduct(string $product).
*/

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

        Route::get('tenants/{slug}/renewal-billing', [SaasProvisionController::class, 'renewalBilling'])
            ->name('api.internal.saas.renewal-billing');

        Route::get('tenants/{slug}', [SaasProvisionController::class, 'status'])
            ->name('api.internal.saas.status');

        Route::get('lookup', [SaasProvisionController::class, 'lookupByEmail'])
            ->name('api.internal.saas.lookup');

        Route::get('showcase', [SaasProvisionController::class, 'showcase'])
            ->name('api.internal.saas.showcase');
    });
