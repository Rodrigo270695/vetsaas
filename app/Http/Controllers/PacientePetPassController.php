<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Paciente;
use App\Services\PetPass\AlmaPetHandoffClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class PacientePetPassController extends Controller
{
    public function start(Request $request, Paciente $paciente, AlmaPetHandoffClient $client): RedirectResponse
    {
        abort_unless($request->user()?->can('petpass.register') ?? false, 403);

        $issued = $client->createHandoff($paciente);

        return redirect()->away($issued['url']);
    }
}
