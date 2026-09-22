<?php

declare(strict_types=1);

use App\Models\Consulta;
use App\Models\ConsultaPlanTratamiento;
use App\Models\ConsultaPlanTratamientoLinea;
use App\Models\ConsultaPlanTratamientoSeguimiento;
use App\Models\User;
use App\Support\Pdf\HistorialClinicoPdfBuilder;
use Carbon\Carbon;
use Illuminate\Support\Collection;

it('incluye el plan de medicación y el seguimiento en el resumen de la consulta', function (): void {
    $consulta = new Consulta([
        'motivo' => 'Control',
        'atendido_at' => Carbon::parse('2026-09-22 18:34:00', 'America/Lima'),
    ]);
    $consulta->setRelation('examenes', new Collection);
    $consulta->setRelation('terapiaLineas', new Collection);
    $consulta->setRelation('recetas', new Collection);
    $consulta->setRelation('pedidosLaboratorio', new Collection);
    $consulta->setRelation('cirugias', new Collection);
    $consulta->setRelation('internamientos', new Collection);

    $linea = new ConsultaPlanTratamientoLinea([
        'medicamento' => 'Amoxicilina',
        'dosis' => '250',
        'unidad' => 'mg',
        'via' => 'VO',
        'frecuencia' => 'cada 12 h',
        'notas' => 'Con comida',
    ]);
    $seguimiento = new ConsultaPlanTratamientoSeguimiento([
        'nota' => 'Come bien',
        'registrado_at' => Carbon::parse('2026-09-22 19:00:00', 'UTC'),
    ]);
    $seguimiento->setRelation('creadoPor', new User(['name' => 'Ana']));

    $plan = new ConsultaPlanTratamiento([
        'estado' => 'activo',
        'indicaciones' => 'Dar con comida',
        'fecha_inicio' => '2026-09-22',
        'fecha_fin' => '2026-09-28',
    ]);
    $plan->setRelation('lineas', new Collection([$linea]));
    $plan->setRelation('seguimientos', new Collection([$seguimiento]));
    $consulta->setRelation('planTratamiento', $plan);

    $entry = (new HistorialClinicoPdfBuilder('America/Lima'))->fromConsulta($consulta);
    $soap = collect($entry['soap'])->keyBy('label');
    $planLabel = __('historial_clinico.plan_medicacion');
    $seguimientoLabel = __('historial_clinico.plan_seguimiento');

    expect($soap->get($planLabel)['text'] ?? '')
        ->toContain('Amoxicilina')
        ->toContain(__('historial_clinico.plan_estado_activo'))
        ->toContain('Dar con comida')
        ->toContain('22/09/2026')
        ->and($soap->get($seguimientoLabel)['text'] ?? '')
        ->toContain('Come bien')
        ->toContain('Ana');
});
