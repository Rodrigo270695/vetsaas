<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\OpenWa\OpenWaClient;
use App\Services\Platform\WhatsAppHealthRadarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class PlataformaWhatsAppHealthController extends Controller
{
    public function index(Request $request, WhatsAppHealthRadarService $radar): Response
    {
        $payload = $radar->paginate(
            trim((string) $request->string('search', '')),
            (string) $request->string('scope', 'listos'),
            (int) $request->integer('per_page', 15),
        );

        return Inertia::render('plataforma/whatsapp-salud/index', $payload);
    }

    /**
     * Equivale a `php artisan vetsaas:whatsapp-sync-sessions --force`
     * más liberar el cooldown 429 (como `cache:forget openwa:rate-limited`).
     */
    public function sync(OpenWaClient $client): RedirectResponse
    {
        $client->clearRateLimited();

        try {
            $exit = Artisan::call('vetsaas:whatsapp-sync-sessions', [
                '--force' => true,
            ]);
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'No se pudo sincronizar WhatsApp: '.$e->getMessage());
        }

        $output = trim(Artisan::output());

        if ($exit !== 0) {
            return back()->with('error', $output !== '' ? $output : 'No se pudo sincronizar WhatsApp.');
        }

        return back()->with(
            'success',
            $output !== '' ? $output : 'Sincronización WhatsApp lanzada.',
        );
    }
}
