<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Platform\OperacionesSnapshotService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Panel de operaciones del SaaS (solo superadmin / host central).
 *
 * Radar de salud de negocio: tenants, WhatsApp, jobs fallidos,
 * suscripciones en riesgo y credenciales globales.
 */
class PlataformaOperacionesController extends Controller
{
    public function index(OperacionesSnapshotService $snapshot): Response
    {
        return Inertia::render('plataforma/operaciones/index', [
            'snapshot' => $snapshot->build(),
            'can_manage' => request()->user()?->can('plataforma-operaciones.manage') ?? false,
        ]);
    }

    public function retryFailedJob(string $uuid): RedirectResponse
    {
        try {
            $exit = Artisan::call('queue:retry', ['id' => [$uuid]]);
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'No se pudo reintentar el job: '.$e->getMessage());
        }

        if ($exit !== 0) {
            return back()->with('error', trim(Artisan::output()) ?: 'No se pudo reintentar el job.');
        }

        return back()->with('success', 'Job reencolado: '.$uuid);
    }

    public function retryAllFailedJobs(): RedirectResponse
    {
        try {
            $exit = Artisan::call('queue:retry', ['id' => ['all']]);
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'No se pudieron reintentar los jobs: '.$e->getMessage());
        }

        if ($exit !== 0) {
            return back()->with('error', trim(Artisan::output()) ?: 'No se pudieron reintentar los jobs.');
        }

        return back()->with('success', 'Todos los jobs fallidos fueron reencolados.');
    }
}
