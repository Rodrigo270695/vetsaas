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
    ) {}

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function execute(string $toolName, array $arguments, string $clientPhone): string
    {
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
