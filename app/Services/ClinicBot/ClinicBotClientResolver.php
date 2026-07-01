<?php

declare(strict_types=1);

namespace App\Services\ClinicBot;

use App\Models\Paciente;
use App\Models\Propietario;
use App\Support\ClinicBot\ClinicBotPhoneMatcher;
use Illuminate\Database\Eloquent\Builder;

final class ClinicBotClientResolver
{
    public function __construct(
        private readonly ClinicBotPhoneMatcher $phoneMatcher,
    ) {}

    public function findPropietarioByPhone(string $phone): ?Propietario
    {
        $variants = $this->phoneMatcher->variants($phone);
        if ($variants === []) {
            return null;
        }

        return Propietario::query()
            ->where('activo', true)
            ->where(function (Builder $query) use ($variants): void {
                foreach ($variants as $variant) {
                    $query->orWhere(function (Builder $inner) use ($variant): void {
                        $inner->whereRaw(
                            "RIGHT(regexp_replace(COALESCE(telefono, ''), '\\D', '', 'g'), ?) = ?",
                            [strlen($variant), $variant],
                        )->orWhereRaw(
                            "RIGHT(regexp_replace(COALESCE(telefono_alt, ''), '\\D', '', 'g'), ?) = ?",
                            [strlen($variant), $variant],
                        );
                    });
                }
            })
            ->first();
    }

    /**
     * @return list<array{id: string, nombre: string, especie: string|null, propietario: string}>
     */
    public function listPacientesForPhone(string $phone): array
    {
        $propietario = $this->findPropietarioByPhone($phone);
        if ($propietario === null) {
            return [];
        }

        return Paciente::query()
            ->where('propietario_id', $propietario->id)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get()
            ->map(fn (Paciente $paciente): array => [
                'id' => $paciente->id,
                'nombre' => $paciente->nombre,
                'especie' => $paciente->especie,
                'propietario' => trim($propietario->nombres.' '.($propietario->apellidos ?? '')),
            ])
            ->all();
    }

    public function pacienteBelongsToPhone(string $pacienteId, string $phone): bool
    {
        $propietario = $this->findPropietarioByPhone($phone);
        if ($propietario === null) {
            return false;
        }

        return Paciente::query()
            ->whereKey($pacienteId)
            ->where('propietario_id', $propietario->id)
            ->where('activo', true)
            ->exists();
    }
}
