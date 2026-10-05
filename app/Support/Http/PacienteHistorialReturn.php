<?php

declare(strict_types=1);

namespace App\Support\Http;

use Illuminate\Http\RedirectResponse;

final class PacienteHistorialReturn
{
    public static function ifFromPaciente(string $message): ?RedirectResponse
    {
        if (! str_contains(url()->previous(), '/clinica/pacientes/')) {
            return null;
        }

        return redirect()->back()->with('success', $message);
    }
}
