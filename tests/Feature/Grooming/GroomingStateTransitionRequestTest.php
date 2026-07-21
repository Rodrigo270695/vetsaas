<?php

use App\Http\Requests\CambiarEstadoGroomingTurnoRequest;
use App\Models\GroomingTurno;
use Illuminate\Validation\Validator;

function groomingStateValidator(string $origen, string $destino): Validator
{
    $turno = new GroomingTurno;
    $turno->estado = $origen;

    $request = CambiarEstadoGroomingTurnoRequest::create('/', 'POST', [
        'estado' => $destino,
    ]);
    $request->setContainer(app());
    $request->setRouteResolver(fn () => new class($turno)
    {
        public function __construct(private readonly GroomingTurno $turno) {}

        public function parameter(string $key, mixed $default = null): mixed
        {
            return $key === 'grooming_turno' ? $this->turno : $default;
        }
    });

    /** @var Validator $validator */
    $validator = validator($request->all(), $request->rules());
    $request->withValidator($validator);

    return $validator;
}

it('permite únicamente las transiciones de estado expuestas por grooming', function (
    string $origen,
    string $destino,
): void {
    expect(groomingStateValidator($origen, $destino)->passes())->toBeTrue();
})->with([
    'programada a en proceso' => ['programada', 'en_proceso'],
    'confirmada a cancelada' => ['confirmada', 'cancelada'],
    'en proceso a completada' => ['en_proceso', 'completada'],
    'en proceso a no asistió' => ['en_proceso', 'no_asistio'],
]);

it('rechaza transiciones repetidas, terminales o fuera del flujo', function (
    string $origen,
    string $destino,
): void {
    expect(groomingStateValidator($origen, $destino)->fails())->toBeTrue();
})->with([
    'estado repetido' => ['en_proceso', 'en_proceso'],
    'turno completado' => ['completada', 'cancelada'],
    'salto a completada' => ['programada', 'completada'],
    'confirmación fuera de la acción' => ['programada', 'confirmada'],
]);
