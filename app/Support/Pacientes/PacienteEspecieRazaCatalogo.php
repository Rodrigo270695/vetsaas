<?php

namespace App\Support\Pacientes;

use App\Models\Paciente;

class PacienteEspecieRazaCatalogo
{
  /** @var list<string> */
    public const ESPECIES_DEFAULT = [
        'Perro',
        'Gato',
        'Conejo',
        'Hurón',
        'Ave',
        'Reptil',
        'Roedor',
    ];

    /** @var list<string> */
    public const RAZAS_DEFAULT = [
        'Mestizo',
        'Cruce',
        'Labrador retriever',
        'Golden retriever',
        'Bulldog francés',
        'Yorkshire terrier',
        'Chihuahua',
        'Pastor alemán',
        'Beagle',
        'Caniche / Poodle',
        'Dálmata',
        'Rottweiler',
        'Boxer',
        'Persa',
        'Siamés',
        'Europeo común',
        'Maine coon',
        'British shorthair',
        'Scottish fold',
    ];

    /**
     * @return list<string>
     */
    public static function especies(): array
    {
        $fromDb = Paciente::query()
            ->whereNotNull('especie')
            ->where('especie', '!=', '')
            ->distinct()
            ->orderBy('especie')
            ->pluck('especie')
            ->map(fn (mixed $v): string => trim((string) $v))
            ->filter()
            ->values()
            ->all();

        return self::merge(self::ESPECIES_DEFAULT, $fromDb);
    }

    /**
     * @return list<string>
     */
    public static function razas(): array
    {
        $fromDb = Paciente::query()
            ->whereNotNull('raza')
            ->where('raza', '!=', '')
            ->distinct()
            ->orderBy('raza')
            ->pluck('raza')
            ->map(fn (mixed $v): string => trim((string) $v))
            ->filter()
            ->values()
            ->all();

        return self::merge(self::RAZAS_DEFAULT, $fromDb);
    }

    /**
     * @return array{especies: list<string>, razas: list<string>}
     */
    public static function payload(): array
    {
        return [
            'especies' => self::especies(),
            'razas' => self::razas(),
        ];
    }

    /**
     * @param  list<string>  $defaults
     * @param  list<string>  $fromDb
     * @return list<string>
     */
    private static function merge(array $defaults, array $fromDb): array
    {
        $unique = [];

        foreach (array_merge($defaults, $fromDb) as $value) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                continue;
            }
            $key = mb_strtolower($trimmed);
            if (! array_key_exists($key, $unique)) {
                $unique[$key] = $trimmed;
            }
        }

        $values = array_values($unique);
        usort($values, static fn (string $a, string $b): int => strnatcasecmp($a, $b));

        return $values;
    }
}
