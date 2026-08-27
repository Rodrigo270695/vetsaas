<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Subscriptions\FreePlanWinBackService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Aceptación pública de win-back Free (enlace del correo).
 */
final class FreeWinBackAcceptController extends Controller
{
    public function __invoke(string $token, FreePlanWinBackService $winBack): Response
    {
        $result = $winBack->acceptByToken($token);

        return Inertia::render('win-back/free-result', [
            'status' => $result['status'],
            'message' => $result['message'],
            'login_url' => $result['login_url'],
            'granted_days' => $result['granted_days'],
        ]);
    }
}
