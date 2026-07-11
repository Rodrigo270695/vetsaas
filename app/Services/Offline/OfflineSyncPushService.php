<?php

declare(strict_types=1);

namespace App\Services\Offline;

use App\Http\Requests\CategoriaProductoRequest;
use App\Http\Requests\CompraInventarioStoreRequest;
use App\Http\Requests\MovimientoInventarioStoreRequest;
use App\Http\Requests\PacienteRequest;
use App\Http\Requests\ProductoInventarioRequest;
use App\Http\Requests\PropietarioRequest;
use App\Http\Requests\ProveedorInventarioRequest;
use App\Http\Requests\SedeRequest;
use App\Http\Requests\StockInventarioAdjustRequest;
use App\Http\Requests\StoreCirugiaRequest;
use App\Http\Requests\StoreCitaRequest;
use App\Http\Requests\StoreConsultaHistoriaRequest;
use App\Http\Requests\StoreGroomingTurnoRequest;
use App\Http\Requests\StoreHotelEstanciaRequest;
use App\Http\Requests\StoreInternamientoEvolucionRequest;
use App\Http\Requests\StoreInternamientoRequest;
use App\Http\Requests\StorePedidoLaboratorioRequest;
use App\Http\Requests\StoreRecetaRequest;
use App\Http\Requests\StoreVacunaAplicadaRequest;
use App\Http\Requests\StoreVentaRequest;
use App\Models\CategoriaProducto;
use App\Models\Cirugia;
use App\Models\Cita;
use App\Models\Compra;
use App\Models\CompraLinea;
use App\Models\Consulta;
use App\Models\Distrito;
use App\Models\ExistenciaSede;
use App\Models\GroomingTurno;
use App\Models\HistoriaClinica;
use App\Models\HotelEstancia;
use App\Models\Internamiento;
use App\Models\InternamientoEvolucion;
use App\Models\MovimientoInventario;
use App\Models\OfflineSyncEvent;
use App\Models\Paciente;
use App\Models\PedidoLaboratorio;
use App\Models\PedidoLaboratorioLinea;
use App\Models\Producto;
use App\Models\Propietario;
use App\Models\Proveedor;
use App\Models\Receta;
use App\Models\RecetaLinea;
use App\Models\Sede;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VacunaAplicada;
use App\Models\Venta;
use App\Services\Venta\VentaCheckoutService;
use App\Services\Inventario\InventarioLoteService;
use App\Support\Grooming\GroomingTurnoServicioRules;
use App\Support\Hotel\HotelEstanciaTipoRules;
use App\Support\Vacunas\VacunaAplicadaStockSync;
use App\Tenancy\TenantManager;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class OfflineSyncPushService
{
    private const MAX_WAIT_SECONDS = 30;

    public function __construct(
        private readonly VentaCheckoutService $checkout,
        private readonly TenantManager $tenants,
    ) {}

    /**
     * @param  array{uuid: string, type: string, payload: array<string, mixed>}  $item
     * @return array<string, mixed>
     */
    public function process(User $user, array $item): array
    {
        $uuid = (string) ($item['uuid'] ?? '');
        $type = (string) ($item['type'] ?? '');
        $payload = is_array($item['payload'] ?? null) ? $item['payload'] : [];

        if ($uuid === '' || $type === '') {
            return $this->failed($uuid, 'Payload inválido.');
        }

        [$event, $isOwner] = $this->acquireOrLoad($user, $uuid, $type, $payload);

        if ($event === null) {
            return $this->failed($uuid, __('offline.sync.error_generico'));
        }

        if (in_array($event->status, ['synced', 'failed'], true)) {
            return $this->resultFromEvent($event);
        }

        if (! $isOwner) {
            return $this->waitForCompletion($uuid);
        }

        return match ($type) {
            'caja.venta.create' => $this->completeVenta($user, $event, $payload),
            'clinica.cita.create' => $this->completeCita($user, $event, $payload),
            'clinica.consulta.create' => $this->completeConsulta($user, $event, $payload),
            'clinica.propietario.create' => $this->completePropietario($user, $event, $payload),
            'clinica.paciente.create' => $this->completePaciente($user, $event, $payload),
            'clinica.vacuna.create' => $this->completeVacuna($user, $event, $payload),
            'clinica.cirugia.create' => $this->completeCirugia($user, $event, $payload),
            'clinica.internamiento.create' => $this->completeInternamiento($user, $event, $payload),
            'clinica.internamiento.evolucion.create' => $this->completeInternamientoEvolucion($user, $event, $payload),
            'servicios.grooming.create' => $this->completeGrooming($user, $event, $payload),
            'servicios.hotel.create' => $this->completeHotel($user, $event, $payload),
            'inventario.movimiento.create' => $this->completeMovimiento($user, $event, $payload),
            'inventario.compra.create' => $this->completeCompra($user, $event, $payload),
            'inventario.producto.create' => $this->completeProducto($user, $event, $payload),
            'inventario.categoria.create' => $this->completeCategoria($user, $event, $payload),
            'inventario.proveedor.create' => $this->completeProveedor($user, $event, $payload),
            'inventario.stock.adjust' => $this->completeStockAdjust($user, $event, $payload),
            'clinica.receta.create' => $this->completeReceta($user, $event, $payload),
            'clinica.laboratorio.create' => $this->completeLaboratorio($user, $event, $payload),
            'configuracion.sede.create' => $this->completeSede($user, $event, $payload),
            default => $this->markFailed($event, __('offline.sync.tipo_no_soportado')),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: OfflineSyncEvent|null, 1: bool}
     */
    private function acquireOrLoad(
        User $user,
        string $uuid,
        string $type,
        array $payload,
    ): array {
        try {
            $event = OfflineSyncEvent::query()->create([
                'client_uuid' => $uuid,
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'type' => $type,
                'payload' => $payload,
                'status' => 'processing',
                'synced_at' => now(),
            ]);

            return [$event, true];
        } catch (QueryException $e) {
            if (! $this->isUniqueViolation($e)) {
                throw $e;
            }

            $existing = OfflineSyncEvent::query()
                ->where('client_uuid', $uuid)
                ->first();

            return [$existing, false];
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function completeVenta(User $user, OfflineSyncEvent $event, array $payload): array
    {
        try {
            $validated = $this->validateVentaPayload($payload, $user);
            $tenant = $this->tenants->current()?->tenant
                ?? Tenant::query()->find($user->tenant_id);

            $venta = DB::transaction(fn () => $this->checkout->registrar($validated, $user, $tenant));

            $event->update([
                'status' => 'synced',
                'resource_type' => Venta::class,
                'resource_id' => $venta->id,
                'resource_label' => $venta->numero,
                'error_message' => null,
                'synced_at' => now(),
            ]);

            return $this->resultFromEvent($event->fresh());
        } catch (ValidationException $e) {
            $message = (string) collect($e->errors())->flatten()->first();

            return $this->markFailed($event, $message);
        } catch (\Throwable $e) {
            report($e);

            return $this->markFailed($event, __('offline.sync.error_generico'));
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function completeCita(User $user, OfflineSyncEvent $event, array $payload): array
    {
        try {
            $validated = $this->validateCitaPayload($payload, $user);
            $uid = $user->id;

            $cita = DB::transaction(function () use ($validated, $uid): Cita {
                return Cita::query()->create([
                    ...$validated,
                    'estado' => Cita::ESTADO_PROGRAMADA,
                    'created_by_id' => $uid,
                    'updated_by_id' => $uid,
                ]);
            });

            $label = $cita->inicio_at->format('Y-m-d H:i');

            $event->update([
                'status' => 'synced',
                'resource_type' => Cita::class,
                'resource_id' => $cita->id,
                'resource_label' => $label,
                'error_message' => null,
                'synced_at' => now(),
            ]);

            return $this->resultFromEvent($event->fresh());
        } catch (ValidationException $e) {
            $message = (string) collect($e->errors())->flatten()->first();

            return $this->markFailed($event, $message);
        } catch (\Throwable $e) {
            report($e);

            return $this->markFailed($event, __('offline.sync.error_generico'));
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function completeConsulta(User $user, OfflineSyncEvent $event, array $payload): array
    {
        try {
            $validated = $this->validateConsultaPayload($payload, $user);
            $uid = $user->id;
            $consultaCreada = null;

            DB::transaction(function () use ($validated, $uid, &$consultaCreada): void {
                $historia = HistoriaClinica::query()->firstOrCreate(
                    ['paciente_id' => $validated['paciente_id']],
                    [
                        'created_by_id' => $uid,
                        'updated_by_id' => $uid,
                    ],
                );

                if ($historia->wasRecentlyCreated === false) {
                    $historia->update(['updated_by_id' => $uid]);
                }

                $peso = $validated['peso_kg'] ?? null;
                $temp = $validated['temperatura_c'] ?? null;
                $fc = $validated['fc_lpm'] ?? null;
                $fr = $validated['fr_rpm'] ?? null;

                $consultaCreada = $historia->consultas()->create([
                    'atendido_at' => $validated['atendido_at'],
                    'motivo' => $validated['motivo'] ?? null,
                    'subjetivo' => $validated['subjetivo'] ?? null,
                    'objetivo' => $validated['objetivo'] ?? null,
                    'analisis' => $validated['analisis'] ?? null,
                    'plan' => $validated['plan'] ?? null,
                    'peso_kg' => $peso === null || $peso === '' ? null : $peso,
                    'temperatura_c' => $temp === null || $temp === '' ? null : $temp,
                    'fc_lpm' => $fc === null || $fc === '' ? null : (int) $fc,
                    'fr_rpm' => $fr === null || $fr === '' ? null : (int) $fr,
                    'cerrada_at' => null,
                    'cerrada_por_id' => null,
                    'veterinario_id' => $uid,
                    'created_by_id' => $uid,
                    'updated_by_id' => $uid,
                ]);
            });

            if ($consultaCreada === null) {
                return $this->markFailed($event, __('offline.sync.error_generico'));
            }

            $event->update([
                'status' => 'synced',
                'resource_type' => Consulta::class,
                'resource_id' => $consultaCreada->id,
                'resource_label' => $consultaCreada->atendido_at->format('Y-m-d H:i'),
                'error_message' => null,
                'synced_at' => now(),
            ]);

            return $this->resultFromEvent($event->fresh());
        } catch (ValidationException $e) {
            $message = (string) collect($e->errors())->flatten()->first();

            return $this->markFailed($event, $message);
        } catch (\Throwable $e) {
            report($e);

            return $this->markFailed($event, __('offline.sync.error_generico'));
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function completePropietario(User $user, OfflineSyncEvent $event, array $payload): array
    {
        try {
            $validated = $this->validatePropietarioPayload($payload, $user);
            $data = $this->hydrateLocationFromDistrito($validated);
            $uid = $user->id;

            $propietario = DB::transaction(function () use ($data, $uid): Propietario {
                return Propietario::query()->create([
                    ...$data,
                    'created_by_id' => $uid,
                    'updated_by_id' => $uid,
                ]);
            });

            $label = $propietario->razon_social
                ?: trim(implode(' ', array_filter([$propietario->nombres, $propietario->apellidos])));

            $event->update([
                'status' => 'synced',
                'resource_type' => Propietario::class,
                'resource_id' => $propietario->id,
                'resource_label' => $label !== '' ? $label : $propietario->id,
                'error_message' => null,
                'synced_at' => now(),
            ]);

            return $this->resultFromEvent($event->fresh());
        } catch (ValidationException $e) {
            $message = (string) collect($e->errors())->flatten()->first();

            return $this->markFailed($event, $message);
        } catch (\Throwable $e) {
            report($e);

            return $this->markFailed($event, __('offline.sync.error_generico'));
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function completePaciente(User $user, OfflineSyncEvent $event, array $payload): array
    {
        try {
            $resolved = $this->resolvePacientePayload($payload);
            $validated = $this->validatePacientePayload($resolved, $user);
            $uid = $user->id;
            $data = collect($validated)->except(['foto', 'clear_foto'])->all();

            $paciente = DB::transaction(function () use ($data, $uid): Paciente {
                return Paciente::query()->create([
                    'propietario_id' => $data['propietario_id'],
                    'nombre' => $data['nombre'],
                    'especie' => $data['especie'] ?? null,
                    'raza' => $data['raza'] ?? null,
                    'sexo' => $data['sexo'] ?? null,
                    'fecha_nacimiento' => $data['fecha_nacimiento'] ?? null,
                    'peso_kg' => $data['peso_kg'] ?? null,
                    'microchip' => $data['microchip'] ?? null,
                    'color' => $data['color'] ?? null,
                    'esterilizado' => array_key_exists('esterilizado', $data) ? $data['esterilizado'] : null,
                    'notas' => $data['notas'] ?? null,
                    'activo' => $data['activo'],
                    'created_by_id' => $uid,
                    'updated_by_id' => $uid,
                ]);
            });

            $event->update([
                'status' => 'synced',
                'resource_type' => Paciente::class,
                'resource_id' => $paciente->id,
                'resource_label' => $paciente->nombre,
                'error_message' => null,
                'synced_at' => now(),
            ]);

            return $this->resultFromEvent($event->fresh());
        } catch (ValidationException $e) {
            $message = (string) collect($e->errors())->flatten()->first();

            return $this->markFailed($event, $message);
        } catch (\Throwable $e) {
            report($e);

            return $this->markFailed($event, __('offline.sync.error_generico'));
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function resolvePacientePayload(array $payload): array
    {
        if (! empty($payload['propietario_id'])) {
            return $payload;
        }

        $clientUuid = $payload['propietario_client_uuid'] ?? null;

        if (! is_string($clientUuid) || $clientUuid === '') {
            return $payload;
        }

        $propietarioId = OfflineSyncEvent::query()
            ->where('client_uuid', $clientUuid)
            ->where('status', 'synced')
            ->value('resource_id');

        if ($propietarioId !== null) {
            $payload['propietario_id'] = $propietarioId;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function completeVacuna(User $user, OfflineSyncEvent $event, array $payload): array
    {
        try {
            $validated = $this->validateVacunaPayload($payload, $user);
            $validated['nombre_vacuna'] = Str::limit(trim($validated['nombre_vacuna']), 500, '');
            $uid = $user->id;
            $vacunaCreada = null;

            DB::transaction(function () use ($validated, $uid, &$vacunaCreada): void {
                /** @var VacunaAplicada $vacuna */
                $vacuna = VacunaAplicada::query()->create([
                    ...$validated,
                    'created_by_id' => $uid,
                    'updated_by_id' => $uid,
                ]);

                if (VacunaAplicadaStockSync::debeDescontar($vacuna)) {
                    $mov = VacunaAplicadaStockSync::registrarSalida(
                        $vacuna,
                        $uid !== null ? (string) $uid : null,
                    );
                    $vacuna->forceFill(['movimiento_inventario_id' => $mov->id])->save();
                }

                $vacunaCreada = $vacuna;
            });

            if ($vacunaCreada === null) {
                return $this->markFailed($event, __('offline.sync.error_generico'));
            }

            $event->update([
                'status' => 'synced',
                'resource_type' => VacunaAplicada::class,
                'resource_id' => $vacunaCreada->id,
                'resource_label' => $vacunaCreada->nombre_vacuna,
                'error_message' => null,
                'synced_at' => now(),
            ]);

            return $this->resultFromEvent($event->fresh());
        } catch (ValidationException $e) {
            $errors = $e->errors();
            if (isset($errors['cantidad'])) {
                return $this->markFailed($event, __('vacunaciones.stock.insufficient_stock'));
            }

            $message = (string) collect($errors)->flatten()->first();

            return $this->markFailed($event, $message);
        } catch (\Throwable $e) {
            report($e);

            return $this->markFailed($event, __('offline.sync.error_generico'));
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function completeMovimiento(User $user, OfflineSyncEvent $event, array $payload): array
    {
        try {
            $validated = $this->validateMovimientoPayload($payload, $user);

            $mov = app(InventarioLoteService::class)->registrarMovimientoManual(
                $validated['tipo'],
                $validated['producto_id'],
                $validated['sede_id'],
                (string) ((float) (string) $validated['cantidad']),
                $validated['notas'] ?? null,
                (string) $user->id,
                isset($validated['numero_lote']) ? (string) $validated['numero_lote'] : null,
                isset($validated['fecha_vencimiento']) ? (string) $validated['fecha_vencimiento'] : null,
            );

            $event->update([
                'status' => 'synced',
                'resource_type' => MovimientoInventario::class,
                'resource_id' => $mov->id,
                'resource_label' => $validated['tipo'],
                'error_message' => null,
                'synced_at' => now(),
            ]);

            return $this->resultFromEvent($event->fresh());
        } catch (ValidationException $e) {
            $message = (string) collect($e->errors())->flatten()->first();

            return $this->markFailed($event, $message);
        } catch (\Throwable $e) {
            report($e);

            return $this->markFailed($event, __('offline.sync.error_generico'));
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function completeCompra(User $user, OfflineSyncEvent $event, array $payload): array
    {
        try {
            $resolved = $this->resolveReferencingUuid($payload, 'proveedor_id', 'proveedor_client_uuid');
            $validated = $this->validateCompraPayload($resolved, $user);
            $uid = $user->id;
            $tid = tenant_id();

            if ($tid === null) {
                return $this->markFailed($event, __('offline.sync.error_generico'));
            }

            $compraCreada = null;

            DB::transaction(function () use ($validated, $uid, &$compraCreada): void {
                $compra = Compra::query()->create([
                    'proveedor_id' => $validated['proveedor_id'] ?? null,
                    'sede_id' => $validated['sede_id'],
                    'fecha_documento' => $validated['fecha_documento'],
                    'numero_documento' => $validated['numero_documento'] ?? null,
                    'serie' => $validated['serie'] ?? null,
                    'moneda' => $validated['moneda'] ?? 'PEN',
                    'total' => $validated['total'] ?? null,
                    'notas' => $validated['notas'] ?? null,
                    'created_by_id' => (string) $uid,
                ]);

                $refDoc = trim(implode('-', array_filter([$validated['serie'] ?? null, $validated['numero_documento'] ?? null])));
                if ($refDoc === '') {
                    $refDoc = 'ref.'.Str::lower(Str::substr((string) $compra->id, 0, 8));
                }

                $moneda = $validated['moneda'] ?? 'PEN';

                foreach ($validated['lineas'] as $i => $linea) {
                    CompraLinea::query()->create([
                        'compra_id' => $compra->id,
                        'producto_id' => $linea['producto_id'],
                        'cantidad' => $linea['cantidad'],
                        'costo_unitario' => $linea['costo_unitario'] ?? null,
                        'orden' => (int) $i,
                    ]);

                    $costoUnit = $linea['costo_unitario'] ?? null;
                    $notasMov = 'Entrada por compra '.$refDoc;
                    if ($costoUnit !== null && $costoUnit !== '') {
                        $notasMov .= ' · '.$moneda.' '.number_format((float) (string) $costoUnit, 2, '.', '').'/u.';
                    }

                    MovimientoInventario::aplicar(
                        $linea['producto_id'],
                        $validated['sede_id'],
                        MovimientoInventario::TIPO_ENTRADA,
                        (string) ((float) (string) $linea['cantidad']),
                        $notasMov,
                        (string) $uid,
                        (string) $compra->id,
                    );
                }

                $compraCreada = $compra;
            });

            if ($compraCreada === null) {
                return $this->markFailed($event, __('offline.sync.error_generico'));
            }

            $label = $compraCreada->numero_documento
                ?: $compraCreada->fecha_documento?->format('Y-m-d')
                ?: $compraCreada->id;

            $event->update([
                'status' => 'synced',
                'resource_type' => Compra::class,
                'resource_id' => $compraCreada->id,
                'resource_label' => (string) $label,
                'error_message' => null,
                'synced_at' => now(),
            ]);

            return $this->resultFromEvent($event->fresh());
        } catch (ValidationException $e) {
            $message = (string) collect($e->errors())->flatten()->first();

            return $this->markFailed($event, $message);
        } catch (\Throwable $e) {
            report($e);

            return $this->markFailed($event, __('offline.sync.error_generico'));
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function completeProducto(User $user, OfflineSyncEvent $event, array $payload): array
    {
        try {
            $validated = $this->validateProductoPayload($payload, $user);
            $uid = $user->id;

            $producto = DB::transaction(function () use ($validated, $uid): Producto {
                return Producto::query()->create([
                    ...$validated,
                    'created_by_id' => $uid,
                    'updated_by_id' => $uid,
                ]);
            });

            $event->update([
                'status' => 'synced',
                'resource_type' => Producto::class,
                'resource_id' => $producto->id,
                'resource_label' => $producto->nombre,
                'error_message' => null,
                'synced_at' => now(),
            ]);

            return $this->resultFromEvent($event->fresh());
        } catch (ValidationException $e) {
            $message = (string) collect($e->errors())->flatten()->first();

            return $this->markFailed($event, $message);
        } catch (\Throwable $e) {
            report($e);

            return $this->markFailed($event, __('offline.sync.error_generico'));
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function completeCategoria(User $user, OfflineSyncEvent $event, array $payload): array
    {
        try {
            $resolved = $this->resolveReferencingUuid($payload, 'parent_id', 'parent_client_uuid');
            $validated = $this->validateCategoriaPayload($resolved, $user);
            $uid = $user->id;

            $categoria = DB::transaction(function () use ($validated, $uid): CategoriaProducto {
                return CategoriaProducto::query()->create([
                    ...$validated,
                    'orden' => CategoriaProducto::generateNextOrden($validated['parent_id'] ?? null),
                    'created_by_id' => $uid,
                    'updated_by_id' => $uid,
                ]);
            });

            $event->update([
                'status' => 'synced',
                'resource_type' => CategoriaProducto::class,
                'resource_id' => $categoria->id,
                'resource_label' => $categoria->nombre,
                'error_message' => null,
                'synced_at' => now(),
            ]);

            return $this->resultFromEvent($event->fresh());
        } catch (ValidationException $e) {
            $message = (string) collect($e->errors())->flatten()->first();

            return $this->markFailed($event, $message);
        } catch (\Throwable $e) {
            report($e);

            return $this->markFailed($event, __('offline.sync.error_generico'));
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function completeProveedor(User $user, OfflineSyncEvent $event, array $payload): array
    {
        try {
            $validated = $this->validateProveedorInventarioPayload($payload, $user);
            $uid = $user->id;

            $proveedor = DB::transaction(function () use ($validated, $uid): Proveedor {
                return Proveedor::query()->create([
                    ...$validated,
                    'created_by_id' => $uid,
                    'updated_by_id' => $uid,
                ]);
            });

            $event->update([
                'status' => 'synced',
                'resource_type' => Proveedor::class,
                'resource_id' => $proveedor->id,
                'resource_label' => $proveedor->razon_social,
                'error_message' => null,
                'synced_at' => now(),
            ]);

            return $this->resultFromEvent($event->fresh());
        } catch (ValidationException $e) {
            $message = (string) collect($e->errors())->flatten()->first();

            return $this->markFailed($event, $message);
        } catch (\Throwable $e) {
            report($e);

            return $this->markFailed($event, __('offline.sync.error_generico'));
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function completeGrooming(User $user, OfflineSyncEvent $event, array $payload): array
    {
        try {
            $resolved = $this->resolveReferencingUuid($payload, 'paciente_id', 'paciente_client_uuid');
            $validated = $this->validateGroomingPayload($resolved, $user);
            $data = GroomingTurnoServicioRules::normalizarParaPersistencia($validated);
            $uid = $user->id;

            $turno = DB::transaction(function () use ($data, $uid): GroomingTurno {
                return GroomingTurno::query()->create([
                    ...$data,
                    'estado' => GroomingTurno::ESTADO_PROGRAMADA,
                    'created_by_id' => $uid,
                    'updated_by_id' => $uid,
                ]);
            });

            $label = $turno->inicio_at->format('Y-m-d H:i');

            $event->update([
                'status' => 'synced',
                'resource_type' => GroomingTurno::class,
                'resource_id' => $turno->id,
                'resource_label' => $label,
                'error_message' => null,
                'synced_at' => now(),
            ]);

            return $this->resultFromEvent($event->fresh());
        } catch (ValidationException $e) {
            $message = (string) collect($e->errors())->flatten()->first();

            return $this->markFailed($event, $message);
        } catch (\Throwable $e) {
            report($e);

            return $this->markFailed($event, __('offline.sync.error_generico'));
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function completeHotel(User $user, OfflineSyncEvent $event, array $payload): array
    {
        try {
            $resolved = $this->resolveReferencingUuid($payload, 'paciente_id', 'paciente_client_uuid');
            $validated = $this->validateHotelPayload($resolved, $user);
            $data = HotelEstanciaTipoRules::normalizarParaPersistencia($validated);
            $uid = $user->id;

            $estancia = DB::transaction(function () use ($data, $uid): HotelEstancia {
                return HotelEstancia::query()->create([
                    ...$data,
                    'estado' => HotelEstancia::ESTADO_PROGRAMADA,
                    'created_by_id' => $uid,
                    'updated_by_id' => $uid,
                ]);
            });

            $label = $estancia->ingreso_at->format('Y-m-d H:i');

            $event->update([
                'status' => 'synced',
                'resource_type' => HotelEstancia::class,
                'resource_id' => $estancia->id,
                'resource_label' => $label,
                'error_message' => null,
                'synced_at' => now(),
            ]);

            return $this->resultFromEvent($event->fresh());
        } catch (ValidationException $e) {
            $message = (string) collect($e->errors())->flatten()->first();

            return $this->markFailed($event, $message);
        } catch (\Throwable $e) {
            report($e);

            return $this->markFailed($event, __('offline.sync.error_generico'));
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function completeCirugia(User $user, OfflineSyncEvent $event, array $payload): array
    {
        try {
            $resolved = $this->resolveReferencingUuid($payload, 'paciente_id', 'paciente_client_uuid');
            $validated = $this->validateCirugiaPayload($resolved, $user);
            $uid = $user->id;
            $data = $validated;
            $data['estado'] = $data['estado'] ?? Cirugia::ESTADO_BORRADOR;

            $cirugia = DB::transaction(function () use ($data, $uid): Cirugia {
                return Cirugia::query()->create([
                    ...$data,
                    'created_by_id' => $uid,
                    'updated_by_id' => $uid,
                ]);
            });

            $label = $cirugia->programada_at->format('Y-m-d H:i');

            $event->update([
                'status' => 'synced',
                'resource_type' => Cirugia::class,
                'resource_id' => $cirugia->id,
                'resource_label' => $label,
                'error_message' => null,
                'synced_at' => now(),
            ]);

            return $this->resultFromEvent($event->fresh());
        } catch (ValidationException $e) {
            $message = (string) collect($e->errors())->flatten()->first();

            return $this->markFailed($event, $message);
        } catch (\Throwable $e) {
            report($e);

            return $this->markFailed($event, __('offline.sync.error_generico'));
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function completeInternamiento(User $user, OfflineSyncEvent $event, array $payload): array
    {
        try {
            $resolved = $this->resolveReferencingUuid($payload, 'paciente_id', 'paciente_client_uuid');
            $validated = $this->validateInternamientoPayload($resolved, $user);
            $uid = $user->id;
            $data = $this->normalizeInternamientoEstadoFechas($validated);
            $data['estado'] = $data['estado'] ?? Internamiento::ESTADO_ACTIVO;

            $internamiento = DB::transaction(function () use ($data, $uid): Internamiento {
                return Internamiento::query()->create([
                    ...$data,
                    'created_by_id' => $uid,
                    'updated_by_id' => $uid,
                ]);
            });

            $label = $internamiento->ingreso_at->format('Y-m-d H:i');

            $event->update([
                'status' => 'synced',
                'resource_type' => Internamiento::class,
                'resource_id' => $internamiento->id,
                'resource_label' => $label,
                'error_message' => null,
                'synced_at' => now(),
            ]);

            return $this->resultFromEvent($event->fresh());
        } catch (ValidationException $e) {
            $message = (string) collect($e->errors())->flatten()->first();

            return $this->markFailed($event, $message);
        } catch (\Throwable $e) {
            report($e);

            return $this->markFailed($event, __('offline.sync.error_generico'));
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function completeInternamientoEvolucion(User $user, OfflineSyncEvent $event, array $payload): array
    {
        try {
            $resolved = $this->resolveReferencingUuid($payload, 'internamiento_id', 'internamiento_client_uuid');
            $internamientoId = $resolved['internamiento_id'] ?? null;

            if (! is_string($internamientoId) || $internamientoId === '') {
                throw ValidationException::withMessages([
                    'internamiento_id' => [__('validation.required', ['attribute' => 'internamiento_id'])],
                ]);
            }

            $validated = $this->validateInternamientoEvolucionPayload($resolved, $user, $internamientoId);
            $uid = $user->id;

            $evolucion = DB::transaction(function () use ($validated, $internamientoId, $uid): InternamientoEvolucion {
                return InternamientoEvolucion::query()->create([
                    ...$validated,
                    'internamiento_id' => $internamientoId,
                    'created_by_id' => $uid,
                    'updated_by_id' => $uid,
                ]);
            });

            $label = $evolucion->registrado_at->format('Y-m-d H:i');

            $event->update([
                'status' => 'synced',
                'resource_type' => InternamientoEvolucion::class,
                'resource_id' => $evolucion->id,
                'resource_label' => $label,
                'error_message' => null,
                'synced_at' => now(),
            ]);

            return $this->resultFromEvent($event->fresh());
        } catch (ValidationException $e) {
            $message = (string) collect($e->errors())->flatten()->first();

            return $this->markFailed($event, $message);
        } catch (\Throwable $e) {
            report($e);

            return $this->markFailed($event, __('offline.sync.error_generico'));
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function completeStockAdjust(User $user, OfflineSyncEvent $event, array $payload): array
    {
        try {
            $validated = $this->validateStockAdjustPayload($payload, $user);

            $anterior = ExistenciaSede::query()
                ->where('producto_id', $validated['producto_id'])
                ->where('sede_id', $validated['sede_id'])
                ->value('cantidad');
            $anteriorF = round((float) (string) ($anterior ?? 0), 3);
            $nuevoF = round((float) (string) $validated['cantidad'], 3);
            $delta = round($nuevoF - $anteriorF, 3);

            if (abs($delta) < 0.0000001) {
                ExistenciaSede::query()->updateOrCreate(
                    [
                        'producto_id' => $validated['producto_id'],
                        'sede_id' => $validated['sede_id'],
                    ],
                    ['cantidad' => $nuevoF],
                );
            } else {
                MovimientoInventario::aplicar(
                    $validated['producto_id'],
                    $validated['sede_id'],
                    MovimientoInventario::TIPO_AJUSTE,
                    (string) $delta,
                    null,
                    (string) $user->id,
                );
            }

            $label = (string) $nuevoF;

            $event->update([
                'status' => 'synced',
                'resource_type' => MovimientoInventario::class,
                'resource_id' => null,
                'resource_label' => $label,
                'error_message' => null,
                'synced_at' => now(),
            ]);

            return $this->resultFromEvent($event->fresh());
        } catch (ValidationException $e) {
            $message = (string) collect($e->errors())->flatten()->first();

            return $this->markFailed($event, $message);
        } catch (\Throwable $e) {
            report($e);

            return $this->markFailed($event, __('offline.sync.error_generico'));
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function completeReceta(User $user, OfflineSyncEvent $event, array $payload): array
    {
        try {
            $resolved = $this->resolveClinicaPatientRefs($payload);
            $validated = $this->validateRecetaPayload($resolved, $user);
            $lineas = $validated['lineas'];
            unset($validated['lineas']);
            $uid = $user->id;
            $data = $validated;
            $data['estado'] = $data['estado'] ?? Receta::ESTADO_BORRADOR;
            $recetaCreada = null;

            DB::transaction(function () use ($data, $lineas, $uid, &$recetaCreada): void {
                $recetaCreada = Receta::query()->create([
                    ...$data,
                    'created_by_id' => $uid,
                    'updated_by_id' => $uid,
                ]);
                $this->syncRecetaLineas($recetaCreada, $lineas);
            });

            if ($recetaCreada === null) {
                return $this->markFailed($event, __('offline.sync.error_generico'));
            }

            $label = $recetaCreada->emitida_at->format('Y-m-d H:i');

            $event->update([
                'status' => 'synced',
                'resource_type' => Receta::class,
                'resource_id' => $recetaCreada->id,
                'resource_label' => $label,
                'error_message' => null,
                'synced_at' => now(),
            ]);

            return $this->resultFromEvent($event->fresh());
        } catch (ValidationException $e) {
            $message = (string) collect($e->errors())->flatten()->first();

            return $this->markFailed($event, $message);
        } catch (\Throwable $e) {
            report($e);

            return $this->markFailed($event, __('offline.sync.error_generico'));
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function completeLaboratorio(User $user, OfflineSyncEvent $event, array $payload): array
    {
        try {
            $resolved = $this->resolveClinicaPatientRefs($payload);
            $validated = $this->validateLaboratorioPayload($resolved, $user);
            $lineas = $validated['lineas'];
            unset($validated['lineas']);
            $uid = $user->id;
            $data = $validated;
            $data['estado'] = $data['estado'] ?? PedidoLaboratorio::ESTADO_BORRADOR;
            $pedidoCreado = null;

            DB::transaction(function () use ($data, $lineas, $uid, &$pedidoCreado): void {
                $pedidoCreado = PedidoLaboratorio::query()->create([
                    ...$data,
                    'created_by_id' => $uid,
                    'updated_by_id' => $uid,
                ]);
                $this->syncLaboratorioLineas($pedidoCreado, $lineas);
            });

            if ($pedidoCreado === null) {
                return $this->markFailed($event, __('offline.sync.error_generico'));
            }

            $label = $pedidoCreado->solicitado_at->format('Y-m-d H:i');

            $event->update([
                'status' => 'synced',
                'resource_type' => PedidoLaboratorio::class,
                'resource_id' => $pedidoCreado->id,
                'resource_label' => $label,
                'error_message' => null,
                'synced_at' => now(),
            ]);

            return $this->resultFromEvent($event->fresh());
        } catch (ValidationException $e) {
            $message = (string) collect($e->errors())->flatten()->first();

            return $this->markFailed($event, $message);
        } catch (\Throwable $e) {
            report($e);

            return $this->markFailed($event, __('offline.sync.error_generico'));
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function completeSede(User $user, OfflineSyncEvent $event, array $payload): array
    {
        try {
            $tenantId = $user->tenant_id;

            if ($tenantId === null) {
                return $this->markFailed($event, __('offline.sync.error_generico'));
            }

            $validated = $this->validateSedePayload($payload, $user);
            $data = $this->hydrateSedeLocationFromDistrito($validated);
            $uid = $user->id;

            $sede = DB::transaction(function () use ($data, $tenantId, $uid): Sede {
                return Sede::query()->create([
                    ...$data,
                    'tenant_id' => $tenantId,
                    'codigo' => Sede::generateNextCode($tenantId),
                    'created_by_id' => $uid,
                    'updated_by_id' => $uid,
                ]);
            });

            $event->update([
                'status' => 'synced',
                'resource_type' => Sede::class,
                'resource_id' => $sede->id,
                'resource_label' => $sede->nombre,
                'error_message' => null,
                'synced_at' => now(),
            ]);

            return $this->resultFromEvent($event->fresh());
        } catch (ValidationException $e) {
            $message = (string) collect($e->errors())->flatten()->first();

            return $this->markFailed($event, $message);
        } catch (\Throwable $e) {
            report($e);

            return $this->markFailed($event, __('offline.sync.error_generico'));
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function resolveReferencingUuid(
        array $payload,
        string $field,
        string $clientUuidField,
    ): array {
        if (! empty($payload[$field])) {
            return $payload;
        }

        $clientUuid = $payload[$clientUuidField] ?? null;

        if (! is_string($clientUuid) || $clientUuid === '') {
            return $payload;
        }

        $resourceId = OfflineSyncEvent::query()
            ->where('client_uuid', $clientUuid)
            ->where('status', 'synced')
            ->value('resource_id');

        if ($resourceId !== null) {
            $payload[$field] = $resourceId;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function resolveClinicaPatientRefs(array $payload): array
    {
        $payload = $this->resolveReferencingUuid($payload, 'paciente_id', 'paciente_client_uuid');

        return $this->resolveReferencingUuid($payload, 'consulta_id', 'consulta_client_uuid');
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     */
    private function syncRecetaLineas(Receta $receta, array $lineas): void
    {
        foreach ($lineas as $idx => $row) {
            $nombre = Str::limit(trim((string) ($row['nombre_medicamento'] ?? '')), 500, '');
            if ($nombre === '') {
                continue;
            }
            $pos = isset($row['posologia']) && is_string($row['posologia']) ? trim($row['posologia']) : '';
            $ins = isset($row['instrucciones']) && is_string($row['instrucciones']) ? trim($row['instrucciones']) : '';
            RecetaLinea::query()->create([
                'receta_id' => $receta->id,
                'producto_id' => $row['producto_id'] ?? null,
                'nombre_medicamento' => $nombre,
                'posologia' => $pos !== '' ? Str::limit($pos, 2000, '') : null,
                'duracion_dias' => $row['duracion_dias'] ?? null,
                'instrucciones' => $ins !== '' ? Str::limit($ins, 2000, '') : null,
                'orden' => (int) ($row['orden'] ?? $idx),
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     */
    private function syncLaboratorioLineas(PedidoLaboratorio $pedido, array $lineas): void
    {
        foreach ($lineas as $idx => $row) {
            $nombre = Str::limit(trim((string) ($row['nombre_examen'] ?? '')), 500, '');
            if ($nombre === '') {
                continue;
            }
            $ind = isset($row['indicaciones']) && is_string($row['indicaciones']) ? trim($row['indicaciones']) : '';
            $res = isset($row['resultado']) && is_string($row['resultado']) ? trim($row['resultado']) : '';

            PedidoLaboratorioLinea::query()->create([
                'pedido_laboratorio_id' => $pedido->id,
                'nombre_examen' => $nombre,
                'indicaciones' => $ind !== '' ? Str::limit($ind, 2000, '') : null,
                'resultado' => $res !== '' ? Str::limit($res, 20000, '') : null,
                'resultado_at' => isset($row['resultado_at']) && $row['resultado_at'] !== null && $row['resultado_at'] !== ''
                    ? Carbon::parse((string) $row['resultado_at'])
                    : null,
                'resultado_archivo_path' => null,
                'resultado_archivo_original_name' => null,
                'orden' => (int) ($row['orden'] ?? $idx),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function waitForCompletion(string $uuid): array
    {
        $deadline = now()->addSeconds(self::MAX_WAIT_SECONDS);

        while (now()->lessThan($deadline)) {
            $event = OfflineSyncEvent::query()->where('client_uuid', $uuid)->first();

            if ($event === null) {
                break;
            }

            if ($event->status !== 'processing') {
                return $this->resultFromEvent($event);
            }

            usleep(200_000);
        }

        return $this->failed($uuid, __('offline.sync.error_generico'));
    }

    /**
     * @return array<string, mixed>
     */
    private function markFailed(OfflineSyncEvent $event, string $message): array
    {
        $event->update([
            'status' => 'failed',
            'error_message' => $message,
            'synced_at' => now(),
        ]);

        return $this->failed($event->client_uuid, $message);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validateVentaPayload(array $payload, User $user): array
    {
        return $this->validateFormRequest(StoreVentaRequest::class, '/caja/ventas', $payload, $user);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validateCitaPayload(array $payload, User $user): array
    {
        return $this->validateFormRequest(StoreCitaRequest::class, '/clinica/citas', $payload, $user);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validateConsultaPayload(array $payload, User $user): array
    {
        return $this->validateFormRequest(
            StoreConsultaHistoriaRequest::class,
            '/clinica/historias-clinicas/consultas',
            $payload,
            $user,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validatePropietarioPayload(array $payload, User $user): array
    {
        return $this->validateFormRequest(PropietarioRequest::class, '/clinica/propietarios', $payload, $user);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validatePacientePayload(array $payload, User $user): array
    {
        return $this->validateFormRequest(PacienteRequest::class, '/clinica/pacientes', $payload, $user);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validateVacunaPayload(array $payload, User $user): array
    {
        return $this->validateFormRequest(
            StoreVacunaAplicadaRequest::class,
            '/clinica/vacunaciones',
            $payload,
            $user,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validateMovimientoPayload(array $payload, User $user): array
    {
        return $this->validateFormRequest(
            MovimientoInventarioStoreRequest::class,
            '/inventario/movimientos',
            $payload,
            $user,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validateCompraPayload(array $payload, User $user): array
    {
        return $this->validateFormRequest(
            CompraInventarioStoreRequest::class,
            '/inventario/compras',
            $payload,
            $user,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validateProductoPayload(array $payload, User $user): array
    {
        return $this->validateFormRequest(
            ProductoInventarioRequest::class,
            '/inventario/productos',
            $payload,
            $user,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validateCategoriaPayload(array $payload, User $user): array
    {
        return $this->validateFormRequest(
            CategoriaProductoRequest::class,
            '/inventario/categorias',
            $payload,
            $user,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validateProveedorInventarioPayload(array $payload, User $user): array
    {
        return $this->validateFormRequest(
            ProveedorInventarioRequest::class,
            '/inventario/proveedores',
            $payload,
            $user,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validateGroomingPayload(array $payload, User $user): array
    {
        return $this->validateFormRequest(
            StoreGroomingTurnoRequest::class,
            '/servicios/grooming',
            $payload,
            $user,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validateHotelPayload(array $payload, User $user): array
    {
        return $this->validateFormRequest(
            StoreHotelEstanciaRequest::class,
            '/servicios/hotel',
            $payload,
            $user,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validateCirugiaPayload(array $payload, User $user): array
    {
        return $this->validateFormRequest(
            StoreCirugiaRequest::class,
            '/clinica/cirugias',
            $payload,
            $user,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validateInternamientoPayload(array $payload, User $user): array
    {
        return $this->validateFormRequest(
            StoreInternamientoRequest::class,
            '/clinica/hospitalizacion',
            $payload,
            $user,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validateInternamientoEvolucionPayload(
        array $payload,
        User $user,
        string $internamientoId,
    ): array {
        unset($payload['internamiento_id'], $payload['internamiento_client_uuid']);

        return $this->validateFormRequest(
            StoreInternamientoEvolucionRequest::class,
            '/clinica/hospitalizacion/'.$internamientoId.'/evoluciones',
            $payload,
            $user,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validateStockAdjustPayload(array $payload, User $user): array
    {
        return $this->validateFormRequest(
            StockInventarioAdjustRequest::class,
            '/inventario/stock',
            $payload,
            $user,
            'PATCH',
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validateRecetaPayload(array $payload, User $user): array
    {
        return $this->validateFormRequest(
            StoreRecetaRequest::class,
            '/clinica/recetas',
            $payload,
            $user,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validateLaboratorioPayload(array $payload, User $user): array
    {
        return $this->validateFormRequest(
            StorePedidoLaboratorioRequest::class,
            '/clinica/laboratorio',
            $payload,
            $user,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validateSedePayload(array $payload, User $user): array
    {
        return $this->validateFormRequest(
            SedeRequest::class,
            '/configuracion/sedes',
            $payload,
            $user,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function hydrateSedeLocationFromDistrito(array $data): array
    {
        $distritoId = $data['distrito_id'] ?? null;

        if ($distritoId === null) {
            $data['distrito'] = null;
            $data['provincia'] = null;
            $data['departamento'] = null;

            return $data;
        }

        $distrito = Distrito::query()
            ->with('provincia.departamento')
            ->find($distritoId);

        if ($distrito === null) {
            $data['distrito'] = null;
            $data['provincia'] = null;
            $data['departamento'] = null;

            return $data;
        }

        $data['distrito'] = $distrito->name;
        $data['provincia'] = $distrito->provincia?->name;
        $data['departamento'] = $distrito->provincia?->departamento?->name;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeInternamientoEstadoFechas(array $data): array
    {
        $tz = config('app.timezone');
        $estado = (string) ($data['estado'] ?? Internamiento::ESTADO_ACTIVO);

        if ($estado === Internamiento::ESTADO_ALTA) {
            if (empty($data['alta_at'])) {
                $data['alta_at'] = now($tz);
            }
        } else {
            $data['alta_at'] = null;
        }

        return $data;
    }

    /**
     * @param  class-string<FormRequest>  $formClass
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validateFormRequest(
        string $formClass,
        string $uri,
        array $payload,
        User $user,
        string $method = 'POST',
    ): array {
        $base = Request::create($uri, $method, $payload);
        $base->setUserResolver(static fn () => $user);

        /** @var FormRequest $form */
        $form = $formClass::createFrom($base);
        $form->setContainer(app());
        $form->setRedirector(app('redirect'));
        $form->validateResolved();

        return $form->validated();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function hydrateLocationFromDistrito(array $data): array
    {
        $distritoId = $data['distrito_id'] ?? null;

        if ($distritoId === null) {
            $data['distrito'] = null;
            $data['provincia'] = null;
            $data['departamento'] = null;

            return $data;
        }

        $distrito = Distrito::query()
            ->with('provincia.departamento')
            ->find($distritoId);

        if ($distrito === null) {
            $data['distrito'] = null;
            $data['provincia'] = null;
            $data['departamento'] = null;

            return $data;
        }

        $data['distrito'] = $distrito->name;
        $data['provincia'] = $distrito->provincia?->name;
        $data['departamento'] = $distrito->provincia?->departamento?->name;

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function resultFromEvent(OfflineSyncEvent $event): array
    {
        if ($event->status === 'failed') {
            return $this->failed(
                $event->client_uuid,
                (string) ($event->error_message ?? __('offline.sync.error_generico')),
            );
        }

        if ($event->status === 'processing') {
            return $this->failed($event->client_uuid, __('offline.sync.error_generico'));
        }

        $result = [
            'uuid' => $event->client_uuid,
            'status' => 'synced',
            'type' => $event->type,
            'resource_id' => $event->resource_id,
            'resource_label' => $event->resource_label,
        ];

        if ($event->type === 'caja.venta.create') {
            $result['venta_id'] = $event->resource_id;
            $result['numero'] = $event->resource_label;
        }

        return $result;
    }

    /**
     * @return array{uuid: string, status: 'failed', error: string}
     */
    private function failed(string $uuid, string $error): array
    {
        return [
            'uuid' => $uuid,
            'status' => 'failed',
            'error' => $error,
        ];
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        $code = (string) $e->getCode();

        return str_contains($e->getMessage(), 'offline_sync_events_client_uuid_unique')
            || $code === '23505'
            || str_contains(strtolower($e->getMessage()), 'unique');
    }
}
