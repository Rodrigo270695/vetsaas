<?php

use App\Models\CategoriaProducto;
use App\Models\Cita;
use App\Models\Cirugia;
use App\Models\Compra;
use App\Models\GroomingTurno;
use App\Models\HistoriaClinica;
use App\Models\HotelEstancia;
use App\Models\Internamiento;
use App\Models\MovimientoInventario;
use App\Models\Paciente;
use App\Models\PedidoLaboratorio;
use App\Models\Producto;
use App\Models\Propietario;
use App\Models\Proveedor;
use App\Models\Receta;
use App\Models\Sede;
use App\Models\VacunaAplicada;
use App\Models\Venta;

return [

  /**
   * Modelos tenant observados para registrar create / update / delete.
   *
   * @var array<class-string, array{modulo: string, label: string}>
   */
    'observed_models' => [
        Paciente::class => ['modulo' => 'pacientes', 'label' => 'nombre'],
        Propietario::class => ['modulo' => 'propietarios', 'label_method' => 'displayName'],
        Cita::class => ['modulo' => 'citas', 'label' => 'motivo'],
        HistoriaClinica::class => ['modulo' => 'historias_clinicas', 'label' => 'id'],
        VacunaAplicada::class => ['modulo' => 'vacunaciones', 'label' => 'id'],
        Receta::class => ['modulo' => 'recetas', 'label' => 'id'],
        PedidoLaboratorio::class => ['modulo' => 'laboratorio', 'label' => 'id'],
        Cirugia::class => ['modulo' => 'cirugias', 'label' => 'procedimiento'],
        Internamiento::class => ['modulo' => 'hospitalizacion', 'label' => 'id'],
        GroomingTurno::class => ['modulo' => 'grooming', 'label' => 'id'],
        HotelEstancia::class => ['modulo' => 'hotel', 'label' => 'tipo_estancia'],
        Producto::class => ['modulo' => 'productos', 'label' => 'nombre'],
        CategoriaProducto::class => ['modulo' => 'categorias_inventario', 'label' => 'nombre'],
        MovimientoInventario::class => ['modulo' => 'movimientos_stock', 'label' => 'id'],
        Compra::class => ['modulo' => 'compras', 'label' => 'numero_documento'],
        Proveedor::class => ['modulo' => 'proveedores', 'label' => 'razon_social'],
        Venta::class => ['modulo' => 'ventas', 'label' => 'numero'],
        Sede::class => ['modulo' => 'sedes', 'label' => 'nombre'],
    ],

    /** Atributos que nunca se guardan en el diff de cambios. */
    'hidden_attributes' => [
        'password',
        'remember_token',
        'updated_at',
        'created_at',
        'deleted_at',
        'created_by_id',
        'updated_by_id',
    ],

];
