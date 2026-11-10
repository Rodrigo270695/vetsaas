<?php

namespace App\Support\Clinica;

use App\Models\HistoriaClinica;
use App\Models\Paciente;
use Illuminate\Support\Facades\Schema;

class HistoriaClinicaCodigo
{
    public static function ensure(Paciente $paciente): ?HistoriaClinica
    {
        if (! Schema::hasTable('historias_clinicas')) {
            return null;
        }

        $historia = HistoriaClinica::query()->firstOrCreate(
            ['paciente_id' => $paciente->id],
            [
                'created_by_id' => $paciente->created_by_id,
                'updated_by_id' => $paciente->updated_by_id,
            ],
        );

        if (Schema::hasColumn('historias_clinicas', 'codigo') && blank($historia->codigo)) {
            $historia->forceFill([
                'codigo' => self::generar($paciente, $historia),
            ])->save();
        }

        return $historia;
    }

    public static function generar(Paciente $paciente, ?HistoriaClinica $actual = null): string
    {
        $paciente->loadMissing('propietario:id,numero_documento');
        $base = self::base($paciente->propietario?->numero_documento);

        $orden = Paciente::query()
            ->where('propietario_id', $paciente->propietario_id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->pluck('id');

        $n = 1;
        foreach ($orden as $id) {
            if ((string) $id === (string) $paciente->id) {
                break;
            }
            $n++;
        }

        if (! Schema::hasColumn('historias_clinicas', 'codigo')) {
            return $base.'-'.str_pad((string) $n, 2, '0', STR_PAD_LEFT);
        }

        do {
            $codigo = $base.'-'.str_pad((string) $n, 2, '0', STR_PAD_LEFT);
            $ocupado = HistoriaClinica::query()
                ->where('codigo', $codigo)
                ->when($actual?->id, fn ($q) => $q->where('id', '!=', $actual->id))
                ->exists();
            $n++;
        } while ($ocupado && $n < 1000);

        return $codigo;
    }

    private static function base(?string $documento): string
    {
        $doc = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $documento));

        if ($doc === '') {
            return 'HC';
        }

        return substr($doc, 0, 20);
    }
}
