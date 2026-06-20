<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OfflineSyncPushRequest extends FormRequest
{
    /** @var list<string> */
    private const SYNC_TYPES = [
        'caja.venta.create',
        'clinica.cita.create',
        'clinica.consulta.create',
        'clinica.propietario.create',
        'clinica.paciente.create',
        'clinica.vacuna.create',
        'clinica.cirugia.create',
        'clinica.internamiento.create',
        'clinica.internamiento.evolucion.create',
        'servicios.grooming.create',
        'servicios.hotel.create',
        'inventario.movimiento.create',
        'inventario.compra.create',
        'inventario.producto.create',
        'inventario.categoria.create',
        'inventario.proveedor.create',
        'inventario.stock.adjust',
        'clinica.receta.create',
        'clinica.laboratorio.create',
        'configuracion.sede.create',
    ];

    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        foreach ($this->input('items', []) as $item) {
            if (! is_array($item)) {
                return false;
            }

            $type = (string) ($item['type'] ?? '');

            if (! $this->userCanSyncType($user, $type)) {
                return false;
            }
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:25'],
            'items.*.uuid' => ['required', 'uuid'],
            'items.*.type' => ['required', 'string', Rule::in(self::SYNC_TYPES)],
            'items.*.payload' => ['required', 'array'],
        ];
    }

    private function userCanSyncType(User $user, string $type): bool
    {
        return match ($type) {
            'caja.venta.create' => $user->can('ventas.create') ?? false,
            'clinica.cita.create' => $user->can('citas.create') ?? false,
            'clinica.consulta.create' => $user->can('historias-clinicas.create') ?? false,
            'clinica.propietario.create' => $user->can('propietarios.create') ?? false,
            'clinica.paciente.create' => $user->can('pacientes.create') ?? false,
            'clinica.vacuna.create' => $user->can('vacunaciones.create') ?? false,
            'clinica.cirugia.create' => $user->can('cirugias.create') ?? false,
            'clinica.internamiento.create' => $user->can('hospitalizacion.create') ?? false,
            'clinica.internamiento.evolucion.create' => $user->can('hospitalizacion.update') ?? false,
            'servicios.grooming.create' => $user->can('grooming.create') ?? false,
            'servicios.hotel.create' => $user->can('hotel.create') ?? false,
            'inventario.movimiento.create' => $user->can('movimientos-stock.create') ?? false,
            'inventario.compra.create' => $user->can('compras.create') ?? false,
            'inventario.producto.create' => $user->can('productos.create') ?? false,
            'inventario.categoria.create' => $user->can('categorias-inventario.create') ?? false,
            'inventario.proveedor.create' => $user->can('proveedores.create') ?? false,
            'inventario.stock.adjust' => $user->can('stock.adjust') ?? false,
            'clinica.receta.create' => $user->can('recetas.create') ?? false,
            'clinica.laboratorio.create' => $user->can('laboratorio.create') ?? false,
            'configuracion.sede.create' => $user->can('sedes.create') ?? false,
            default => false,
        };
    }
}
