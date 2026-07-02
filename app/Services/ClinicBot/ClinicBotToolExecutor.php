<?php

declare(strict_types=1);

namespace App\Services\ClinicBot;

use App\Support\ClinicBot\ClinicBotPeruClock;

final class ClinicBotToolExecutor
{
    public function __construct(
        private readonly ClinicBotCatalogService $catalog,
        private readonly ClinicBotClientResolver $clientResolver,
        private readonly ClinicBotAppointmentService $appointments,
        private readonly ClinicBotRegistrationService $registration,
    ) {}

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function execute(
        string $toolName,
        array $arguments,
        string $clientPhone,
        ?string $clientName = null,
    ): string {
        $result = match ($toolName) {
            'obtener_fecha_actual' => [
                'referencia' => ClinicBotPeruClock::promptReference(),
                'timezone' => ClinicBotPeruClock::TIMEZONE,
            ],
            'resolver_fecha' => $this->appointments->resolveDateExpression(
                (string) ($arguments['expresion'] ?? ''),
            ),
            'listar_productos' => [
                'productos' => $this->catalog->listProducts(
                    isset($arguments['busqueda']) ? (string) $arguments['busqueda'] : null,
                ),
            ],
            'listar_servicios_grooming' => [
                'servicios' => $this->catalog->listGroomingServices(),
            ],
            'listar_mascotas_cliente' => [
                'mascotas' => $this->clientResolver->listPacientesForPhone($clientPhone),
            ],
            'registrar_propietario' => $this->registration->registerPropietario(
                $clientPhone,
                (string) ($arguments['nombres'] ?? ''),
                isset($arguments['apellidos']) ? (string) $arguments['apellidos'] : null,
            ),
            'registrar_mascota' => $this->registration->registerPaciente(
                $clientPhone,
                (string) ($arguments['nombre'] ?? ''),
                isset($arguments['especie']) ? (string) $arguments['especie'] : null,
                isset($arguments['raza']) ? (string) $arguments['raza'] : null,
                isset($arguments['edad_anios']) ? (int) $arguments['edad_anios'] : null,
                isset($arguments['propietario_nombres']) ? (string) $arguments['propietario_nombres'] : null,
                isset($arguments['propietario_apellidos']) ? (string) $arguments['propietario_apellidos'] : null,
            ),
            'registrar_cita' => $this->appointments->registerCita(
                $clientPhone,
                (string) ($arguments['paciente_id'] ?? ''),
                (string) ($arguments['fecha'] ?? ''),
                (string) ($arguments['hora'] ?? ''),
                isset($arguments['motivo']) ? (string) $arguments['motivo'] : null,
                isset($arguments['duracion_minutos']) ? (int) $arguments['duracion_minutos'] : null,
            ),
            'registrar_turno_grooming' => $this->appointments->registerGroomingTurno(
                $clientPhone,
                (string) ($arguments['paciente_id'] ?? ''),
                (string) ($arguments['servicio_id'] ?? ''),
                (string) ($arguments['fecha'] ?? ''),
                (string) ($arguments['hora'] ?? ''),
                isset($arguments['duracion_minutos']) ? (int) $arguments['duracion_minutos'] : null,
            ),
            default => ['ok' => false, 'error' => 'Herramienta desconocida.'],
        };

        return json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
