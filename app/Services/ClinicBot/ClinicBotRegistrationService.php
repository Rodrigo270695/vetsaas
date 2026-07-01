<?php

declare(strict_types=1);

namespace App\Services\ClinicBot;

use App\Models\Paciente;
use App\Models\Propietario;
use App\Support\ClinicBot\ClinicBotPeruClock;
use App\Support\ClinicBot\ClinicBotPhoneMatcher;
use App\Support\Plan\PlanLimits;
use Illuminate\Support\Carbon;

final class ClinicBotRegistrationService
{
    public function __construct(
        private readonly ClinicBotClientResolver $clientResolver,
        private readonly ClinicBotPhoneMatcher $phoneMatcher,
    ) {}

    /**
     * @return array{ok: true, propietario_id: string, nombres: string, ya_existia: bool}|array{ok: false, error: string}
     */
    public function registerPropietario(
        string $phone,
        string $nombres,
        ?string $apellidos = null,
    ): array {
        if ($this->phoneMatcher->variants($phone) === []) {
            return [
                'ok' => false,
                'error' => 'No se pudo identificar el número de WhatsApp para el registro.',
            ];
        }

        $existing = $this->clientResolver->findPropietarioByPhone($phone);
        if ($existing !== null) {
            return [
                'ok' => true,
                'propietario_id' => $existing->id,
                'nombres' => trim($existing->nombres.' '.($existing->apellidos ?? '')),
                'ya_existia' => true,
            ];
        }

        $nombres = trim($nombres);
        if ($nombres === '') {
            return ['ok' => false, 'error' => 'Indica el nombre del propietario.'];
        }

        if (PlanLimits::tenant() !== null && PlanLimits::wouldExceed('max_propietarios')) {
            return ['ok' => false, 'error' => PlanLimits::message('max_propietarios')];
        }

        $propietario = Propietario::query()->create([
            'nombres' => $nombres,
            'apellidos' => $this->nullableTrim($apellidos),
            'telefono' => $this->formatPhoneForStorage($phone),
            'activo' => true,
            'notas' => 'Registrado automáticamente por el asistente WhatsApp IA.',
        ]);

        return [
            'ok' => true,
            'propietario_id' => $propietario->id,
            'nombres' => trim($propietario->nombres.' '.($propietario->apellidos ?? '')),
            'ya_existia' => false,
        ];
    }

    /**
     * @return array{ok: true, paciente_id: string, nombre: string, propietario_id: string}|array{ok: false, error: string}
     */
    public function registerPaciente(
        string $phone,
        string $nombre,
        ?string $especie = null,
        ?string $raza = null,
        ?int $edadAnios = null,
        ?string $propietarioNombres = null,
        ?string $propietarioApellidos = null,
        ?string $whatsappDisplayName = null,
    ): array {
        $nombre = trim($nombre);
        if ($nombre === '') {
            return ['ok' => false, 'error' => 'Indica el nombre de la mascota.'];
        }

        $propietarioResult = $this->ensurePropietario(
            $phone,
            $propietarioNombres,
            $propietarioApellidos,
            $whatsappDisplayName,
        );

        if ($propietarioResult['ok'] === false) {
            return $propietarioResult;
        }

        $propietarioId = $propietarioResult['propietario_id'];

        $duplicate = Paciente::query()
            ->where('propietario_id', $propietarioId)
            ->where('activo', true)
            ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])
            ->first();

        if ($duplicate !== null) {
            return [
                'ok' => true,
                'paciente_id' => $duplicate->id,
                'nombre' => $duplicate->nombre,
                'propietario_id' => $propietarioId,
                'ya_existia' => true,
            ];
        }

        if (PlanLimits::tenant() !== null && PlanLimits::wouldExceed('max_pacientes')) {
            return ['ok' => false, 'error' => PlanLimits::message('max_pacientes')];
        }

        $paciente = Paciente::query()->create([
            'propietario_id' => $propietarioId,
            'nombre' => $nombre,
            'especie' => $this->nullableTrim($especie),
            'raza' => $this->nullableTrim($raza),
            'fecha_nacimiento' => $this->birthDateFromAge($edadAnios),
            'activo' => true,
            'notas' => 'Registrado automáticamente por el asistente WhatsApp IA.',
        ]);

        return [
            'ok' => true,
            'paciente_id' => $paciente->id,
            'nombre' => $paciente->nombre,
            'propietario_id' => $propietarioId,
            'ya_existia' => false,
        ];
    }

    /**
     * @return array{ok: true, propietario_id: string}|array{ok: false, error: string}
     */
    private function ensurePropietario(
        string $phone,
        ?string $nombres,
        ?string $apellidos,
        ?string $whatsappDisplayName,
    ): array {
        $existing = $this->clientResolver->findPropietarioByPhone($phone);
        if ($existing !== null) {
            return ['ok' => true, 'propietario_id' => $existing->id];
        }

        $resolvedNames = $this->resolveOwnerNames($nombres, $apellidos, $whatsappDisplayName);

        return $this->registerPropietario(
            $phone,
            $resolvedNames['nombres'],
            $resolvedNames['apellidos'],
        );
    }

    /**
     * @return array{nombres: string, apellidos: string|null}
     */
    private function resolveOwnerNames(
        ?string $nombres,
        ?string $apellidos,
        ?string $whatsappDisplayName,
    ): array {
        $nombres = $this->nullableTrim($nombres);
        $apellidos = $this->nullableTrim($apellidos);

        if ($nombres !== null) {
            return [
                'nombres' => $nombres,
                'apellidos' => $apellidos,
            ];
        }

        return $this->splitDisplayName($whatsappDisplayName);
    }

    /**
     * @return array{nombres: string, apellidos: string|null}
     */
    private function splitDisplayName(?string $displayName): array
    {
        $displayName = trim((string) $displayName);
        if ($displayName === '') {
            return ['nombres' => 'Cliente WhatsApp', 'apellidos' => null];
        }

        $parts = preg_split('/\s+/', $displayName) ?: [];
        $first = array_shift($parts);

        return [
            'nombres' => $first !== null && $first !== '' ? $first : 'Cliente WhatsApp',
            'apellidos' => $parts !== [] ? implode(' ', $parts) : null,
        ];
    }

    private function birthDateFromAge(?int $edadAnios): ?Carbon
    {
        if ($edadAnios === null || $edadAnios < 0 || $edadAnios > 40) {
            return null;
        }

        return ClinicBotPeruClock::now()->subYears($edadAnios)->startOfDay();
    }

    private function formatPhoneForStorage(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';
        if (strlen($digits) === 11 && str_starts_with($digits, '51')) {
            return '+51 '.substr($digits, 2, 3).' '.substr($digits, 5, 3).' '.substr($digits, 8);
        }

        if (strlen($digits) === 9) {
            return '+51 '.$digits;
        }

        return $digits;
    }

    private function nullableTrim(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
