<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\OpenWa\TenantWhatsAppPresenter;
use App\Support\Subscriptions\BotIaAccess;
use App\Support\Subscriptions\SubscriptionBotIaAddon;
use App\Tenancy\TenantManager;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Panel del tenant para gestionar el add-on Asistente IA WhatsApp.
 */
class ClinicBotIaController extends Controller
{
    public function show(Request $request, TenantManager $tenants, TenantWhatsAppPresenter $whatsapp): Response
    {
        abort_unless(BotIaAccess::userCanView($request->user()), 403);

        $tenant = $tenants->current()?->tenant;
        abort_if($tenant === null, 404);

        $subscription = $tenant->subscriptions()
            ->orderByDesc('created_at')
            ->first();

        $botIa = SubscriptionBotIaAddon::payload($subscription);
        $canManage = BotIaAccess::userCanManage($request->user(), $tenant);

        return Inertia::render('comunicaciones/bot-ia/index', [
            'bot_ia' => $botIa,
            'whatsapp' => $whatsapp->forTenant($tenant),
            'can_manage' => $canManage,
        ]);
    }
}
