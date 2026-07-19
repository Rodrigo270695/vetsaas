<?php

use App\Http\Controllers\AlertaStockInventarioController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\BotIaAnnouncementController;
use App\Http\Controllers\CajaSesionController;
use App\Http\Controllers\CategoriaInventarioController;
use App\Http\Controllers\CirugiaController;
use App\Http\Controllers\CitaController;
use App\Http\Controllers\ClinicalHistoryWhatsAppController;
use App\Http\Controllers\ClinicBotIaController;
use App\Http\Controllers\ClinicSettingController;
use App\Http\Controllers\ClinicSubscriptionController;
use App\Http\Controllers\CompraInventarioController;
use App\Http\Controllers\ConsultaCargoController;
use App\Http\Controllers\ConsultaDictationController;
use App\Http\Controllers\ConsultaHistoriaController;
use App\Http\Controllers\ConsultaPlanTratamientoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FelDocumentController;
use App\Http\Controllers\FelSerieController;
use App\Http\Controllers\GeoController;
use App\Http\Controllers\GroomingInsumoController;
use App\Http\Controllers\GroomingServicioController;
use App\Http\Controllers\GroomingTurnoController;
use App\Http\Controllers\HospitalizacionController;
use App\Http\Controllers\HotelEstanciaController;
use App\Http\Controllers\HotelTipoEstanciaController;
use App\Http\Controllers\InAppAssistantController;
use App\Http\Controllers\InternamientoCargoController;
use App\Http\Controllers\LaboratorioController;
use App\Http\Controllers\MovimientoInventarioController;
use App\Http\Controllers\NotificationQueueController;
use App\Http\Controllers\OfflineSyncController;
use App\Http\Controllers\PacienteController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\PlataformaImpersonationAuditController;
use App\Http\Controllers\PlataformaOperacionesController;
use App\Http\Controllers\PlatformRenewalReminderController;
use App\Http\Controllers\PlatformSettingController;
use App\Http\Controllers\InAppAssistantAnnouncementController;
use App\Http\Controllers\PlatformWhatsAppController;
use App\Http\Controllers\PresenceHeartbeatController;
use App\Http\Controllers\ProductoInventarioController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\PropietarioController;
use App\Http\Controllers\ProveedorInventarioController;
use App\Http\Controllers\RecetaController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SalesBotConversationController;
use App\Http\Controllers\SalesBotKnowledgeController;
use App\Http\Controllers\SedeController;
use App\Http\Controllers\StockInventarioController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\SubscriptionPaymentController;
use App\Http\Controllers\TarifaServiciosController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\TenantImpersonationController;
use App\Http\Controllers\TenantModuleController;
use App\Http\Controllers\TenantWhatsAppController;
use App\Http\Controllers\TenantWhatsAppPlatformController;
use App\Http\Controllers\UnidadMedidaInventarioController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VacunacionController;
use App\Http\Controllers\VentaController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::redirect('/manifest.webmanifest', '/manifest.json', 301);

/*
|--------------------------------------------------------------------------
| Rutas compartidas (panel central + tenants)
|--------------------------------------------------------------------------
|
| Arquitectura "single-login + datos aislados":
|
|   El MISMO conjunto de rutas (dashboard, configuración, módulos
|   operativos, plataforma) responde tanto en el host central
|   (`localhost`) como en cualquier subdominio de tenant
|   (`mi-clinica.localhost`). La diferencia es qué VE cada usuario:
|     · Los items del sidebar se filtran por permisos de Spatie.
|     · El middleware `tenant.match-user` impide que un empleado de
|       una clínica entre a otra clínica o al panel central, y
|       viceversa.
|     · Las rutas con `permission:plataforma-*` quedan automáticamente
|       fuera del alcance de empleados de clínica (no tienen ese
|       permiso).
|
|   La landing pública de cada clínica (welcome) sigue viviendo en
|   `routes/tenant.php`, porque es accesible sin login.
|
*/

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

/*
|--------------------------------------------------------------------------
| Cambio obligatorio de contraseña (Fase 2.6)
|--------------------------------------------------------------------------
| Rutas accesibles solo a usuarios autenticados, SIN el middleware
| `force-password-change` (porque son justamente las rutas a las que ese
| middleware redirige). Quedan fuera del grupo principal para evitar el
| bucle infinito de redirección.
*/
Route::middleware(['auth', 'tenant.match-user'])->group(function (): void {
    Route::get('cuenta/cambiar-password', [ChangePasswordController::class, 'show'])
        ->name('password.change.form');
    Route::post('cuenta/cambiar-password', [ChangePasswordController::class, 'update'])
        ->name('password.change.update');

    // Heartbeat de presencia (ventana abierta). Sin force-password-change
    // para que también cuente usuarios en flujo de cambio de clave.
    Route::post('presence/heartbeat', PresenceHeartbeatController::class)
        ->middleware('throttle:30,1')
        ->name('presence.heartbeat');
});

Route::middleware(['throttle:12,1'])->group(function (): void {
    Route::get('impersonate/accept', [TenantImpersonationController::class, 'accept'])
        ->name('impersonate.accept');
});

Route::middleware(['auth', 'verified', 'tenant.match-user', 'force-password-change', 'permission:dashboard.view'])
    ->group(function (): void {
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('dashboard/rentabilidad', [DashboardController::class, 'rentabilidad'])
            ->name('dashboard.rentabilidad');
        Route::get('dashboard/rentabilidad-grooming', [DashboardController::class, 'rentabilidadGrooming'])
            ->name('dashboard.rentabilidad-grooming');
    });

/*
|--------------------------------------------------------------------------
| Módulos de negocio (placeholders por ahora)
|--------------------------------------------------------------------------
| Todas las rutas requieren autenticación + email verificado.
| Cada item del sidebar tiene su propia ruta nombrada (modulo.item) y una
| vista Inertia en `resources/js/pages/<modulo>/<item>/index.tsx`.
|
| Cuando construyamos el CRUD real de un módulo, cambiamos `Route::inertia`
| por un Controller dedicado (Route::resource o Route::get con Controller).
*/

Route::middleware(['auth', 'verified', 'tenant.match-user', 'force-password-change'])->group(function () {
    /*
    |--------------------------------------------------------------------------
    | Catálogo geográfico (lecturas internas, sin permiso específico)
    |--------------------------------------------------------------------------
    | Endpoints JSON usados por los combobox en cascada de formularios
    | (sedes, owners, tenants, etc.). Solo expone listas filtradas por
    | el padre seleccionado. No editable desde la app.
    */
    Route::prefix('geo')->name('geo.')->group(function () {
        Route::get('departamentos', [GeoController::class, 'departamentos'])->name('departamentos');
        Route::get('provincias', [GeoController::class, 'provincias'])->name('provincias');
        Route::get('distritos', [GeoController::class, 'distritos'])->name('distritos');
    });

    // Asistente in-app (ayuda + consulta de solo lectura).
    // Clínica (tenant) o portal central (superadmin).
    Route::prefix('asistente')
        ->name('asistente.')
        ->group(function (): void {
            Route::get('status', [InAppAssistantController::class, 'status'])->name('status');
            Route::post('chat', [InAppAssistantController::class, 'chat'])->name('chat');
        });

    Route::post('impersonate/leave', [TenantImpersonationController::class, 'leave'])
        ->name('impersonate.leave');

    // Modo offline — bootstrap de cache y cola de sync (JSON).
    Route::middleware('tenant.required')
        ->prefix('offline')
        ->name('offline.')
        ->group(function (): void {
            Route::get('cola', [OfflineSyncController::class, 'cola'])
                ->name('cola');
            Route::get('bootstrap', [OfflineSyncController::class, 'bootstrap'])
                ->name('bootstrap');
            Route::post('sync/push', [OfflineSyncController::class, 'push'])
                ->name('sync.push');
        });

    // ===== Clínica (datos en schema del tenant; requiere subdominio) =====
    Route::prefix('clinica')->name('clinica.')->group(function () {
        Route::middleware('tenant.required')->group(function () {
            Route::middleware('permission:propietarios.view')
                ->get('propietarios/export', [PropietarioController::class, 'export'])
                ->name('propietarios.export');
            Route::middleware('permission:propietarios.create')
                ->get('propietarios/plantilla-importacion', [PropietarioController::class, 'downloadImportTemplate'])
                ->name('propietarios.import-template');
            Route::middleware('permission:propietarios.create')
                ->post('propietarios/importar', [PropietarioController::class, 'importExcel'])
                ->name('propietarios.import');
            Route::middleware('permission:propietarios.bulk-delete')
                ->delete('propietarios/bulk', [PropietarioController::class, 'bulkDestroy'])
                ->name('propietarios.bulk-destroy');
            Route::middleware('permission:propietarios.create|propietarios.update')
                ->middleware('throttle:20,1')
                ->get('propietarios/consulta-ruc', [PropietarioController::class, 'consultaRuc'])
                ->name('propietarios.consulta-ruc');
            Route::middleware('permission:propietarios.create|propietarios.update')
                ->middleware('throttle:20,1')
                ->get('propietarios/consulta-dni', [PropietarioController::class, 'consultaDni'])
                ->name('propietarios.consulta-dni');
            Route::middleware('permission:propietarios.view')
                ->get('propietarios', [PropietarioController::class, 'index'])
                ->name('propietarios.index');
            Route::middleware('permission:propietarios.create')
                ->post('propietarios', [PropietarioController::class, 'store'])
                ->name('propietarios.store');
            Route::middleware('permission:propietarios.view')
                ->get('propietarios/{propietario}', [PropietarioController::class, 'show'])
                ->name('propietarios.show');
            Route::middleware('permission:propietarios.update')
                ->match(['put', 'patch'], 'propietarios/{propietario}', [PropietarioController::class, 'update'])
                ->name('propietarios.update');
            Route::middleware('permission:propietarios.delete')
                ->delete('propietarios/{propietario}', [PropietarioController::class, 'destroy'])
                ->name('propietarios.destroy');

            Route::middleware('permission:pacientes.create')
                ->post('propietarios/{propietario}/pacientes', [PacienteController::class, 'store'])
                ->name('propietarios.pacientes.store');

            Route::middleware('permission:pacientes.view')
                ->get('pacientes/export', [PacienteController::class, 'export'])
                ->name('pacientes.export');
            Route::middleware('permission:pacientes.create')
                ->get('pacientes/plantilla-importacion', [PacienteController::class, 'downloadImportTemplate'])
                ->name('pacientes.import-template');
            Route::middleware('permission:pacientes.create')
                ->post('pacientes/importar', [PacienteController::class, 'importExcel'])
                ->name('pacientes.import');
            Route::middleware('permission:pacientes.bulk-delete')
                ->delete('pacientes/bulk', [PacienteController::class, 'bulkDestroy'])
                ->name('pacientes.bulk-destroy');
            Route::middleware('permission:pacientes.view')
                ->get('pacientes/catalogo-especie-raza', [PacienteController::class, 'catalogoEspecieRaza'])
                ->name('pacientes.catalogo-especie-raza');
            Route::middleware('permission:pacientes.view')
                ->get('pacientes', [PacienteController::class, 'index'])
                ->name('pacientes.index');
            Route::middleware('permission:pacientes.view')
                ->get('pacientes/{paciente}', [PacienteController::class, 'show'])
                ->name('pacientes.show');
            Route::middleware('permission:pacientes.view')
                ->get('pacientes/{paciente}/historial-clinico.pdf', [PacienteController::class, 'historialClinicoPdf'])
                ->name('pacientes.historial-clinico-pdf');
            Route::middleware('permission:pacientes.view')
                ->post('pacientes/{paciente}/historial-clinico/whatsapp', [ClinicalHistoryWhatsAppController::class, 'historial'])
                ->name('pacientes.historial-clinico-whatsapp');
            Route::middleware('permission:laboratorio.create')
                ->post('pacientes/{paciente}/laboratorio-rapido', [PacienteController::class, 'storeLaboratorioRapido'])
                ->name('pacientes.laboratorio-rapido');
            Route::middleware('permission:pacientes.create')
                ->post('pacientes', [PacienteController::class, 'store'])
                ->name('pacientes.store');
            Route::middleware('permission:vacunaciones.view')
                ->get('pacientes/{paciente}/carnet-vacunacion.pdf', [VacunacionController::class, 'carnetPdf'])
                ->name('pacientes.carnet-vacunacion-pdf');
            Route::middleware('permission:pacientes.update')
                ->match(['put', 'patch'], 'pacientes/{paciente}', [PacienteController::class, 'update'])
                ->name('pacientes.update');
            Route::middleware('permission:pacientes.delete')
                ->delete('pacientes/{paciente}', [PacienteController::class, 'destroy'])
                ->name('pacientes.destroy');

            Route::middleware('permission:historias-clinicas.view')
                ->get('historias-clinicas', [ConsultaHistoriaController::class, 'index'])
                ->name('historias-clinicas');
            Route::middleware('permission:historias-clinicas.view')
                ->get('historias-clinicas/consultas/{consulta}/pdf', [ConsultaHistoriaController::class, 'pdf'])
                ->name('historias-clinicas.consultas.pdf');
            Route::middleware('permission:historias-clinicas.view')
                ->post('historias-clinicas/consultas/{consulta}/whatsapp', [ClinicalHistoryWhatsAppController::class, 'consulta'])
                ->name('historias-clinicas.consultas.whatsapp');
            Route::middleware('permission:historias-clinicas-planes.manage')
                ->get('historias-clinicas/productos-medicamento', [ConsultaPlanTratamientoController::class, 'productosMedicamento'])
                ->name('historias-clinicas.productos-medicamento');
            Route::middleware('permission:historias-clinicas.create')
                ->post('historias-clinicas/consultas', [ConsultaHistoriaController::class, 'store'])
                ->name('historias-clinicas.consultas.store');
            Route::middleware('permission:historias-clinicas.create|historias-clinicas.update')
                ->post('historias-clinicas/consultas/dictar', ConsultaDictationController::class)
                ->name('historias-clinicas.consultas.dictar');
            Route::middleware('permission:historias-clinicas.update')
                ->post('historias-clinicas/consultas/cerrar-abiertas', [ConsultaHistoriaController::class, 'cerrarAbiertas'])
                ->name('historias-clinicas.consultas.cerrar-abiertas');
            Route::middleware('permission:historias-clinicas.update')
                ->match(['put', 'patch'], 'historias-clinicas/consultas/{consulta}', [ConsultaHistoriaController::class, 'update'])
                ->name('historias-clinicas.consultas.update');
            Route::middleware('permission:historias-clinicas.update')
                ->post('historias-clinicas/consultas/{consulta}/cerrar', [ConsultaHistoriaController::class, 'cerrar'])
                ->name('historias-clinicas.consultas.cerrar');
            Route::middleware('permission:historias-clinicas.update')
                ->post('historias-clinicas/consultas/{consulta}/reabrir', [ConsultaHistoriaController::class, 'reabrir'])
                ->name('historias-clinicas.consultas.reabrir');
            Route::middleware('permission:historias-clinicas.delete')
                ->delete('historias-clinicas/consultas/{consulta}', [ConsultaHistoriaController::class, 'destroy'])
                ->name('historias-clinicas.consultas.destroy');
            Route::middleware('permission:historias-clinicas-planes.view')
                ->get('historias-clinicas/consultas/{consulta}/plan-tratamiento', [ConsultaPlanTratamientoController::class, 'planTratamiento'])
                ->name('historias-clinicas.consultas.plan-tratamiento');
            Route::middleware('permission:historias-clinicas-planes.manage')
                ->match(['put', 'patch'], 'historias-clinicas/consultas/{consulta}/plan-tratamiento', [ConsultaPlanTratamientoController::class, 'upsert'])
                ->name('historias-clinicas.consultas.plan-tratamiento.update');
            Route::middleware('permission:historias-clinicas-planes.manage')
                ->post('historias-clinicas/consultas/{consulta}/plan-tratamiento/seguimientos', [ConsultaPlanTratamientoController::class, 'storeSeguimiento'])
                ->name('historias-clinicas.consultas.plan-tratamiento.seguimientos.store');

            Route::middleware('permission:consulta-cargos.view|historias-clinicas.view')
                ->get('historias-clinicas/consultas/{consulta}/cargos', [ConsultaCargoController::class, 'show'])
                ->name('historias-clinicas.consultas.cargos.show');
            Route::middleware('permission:consulta-cargos.view|historias-clinicas.view')
                ->get('historias-clinicas/consultas/{consulta}/cargos/ticket', [ConsultaCargoController::class, 'ticket'])
                ->name('historias-clinicas.consultas.cargos.ticket');
            Route::middleware('permission:consulta-cargos.view|consulta-cargos.manage|productos.view')
                ->get('historias-clinicas/consultas/{consulta}/cargos/productos-buscar', [ConsultaCargoController::class, 'productosBuscar'])
                ->name('historias-clinicas.consultas.cargos.productos-buscar');
            Route::middleware('permission:consulta-cargos.manage|historias-clinicas.update')
                ->match(['put', 'patch'], 'historias-clinicas/consultas/{consulta}/cargos', [ConsultaCargoController::class, 'update'])
                ->name('historias-clinicas.consultas.cargos.update');
            Route::middleware('permission:consulta-cargos.manage|historias-clinicas.update')
                ->post('historias-clinicas/consultas/{consulta}/cargos/confirmar', [ConsultaCargoController::class, 'confirmar'])
                ->name('historias-clinicas.consultas.cargos.confirmar');

            Route::middleware('permission:vacunaciones.view')
                ->get('vacunaciones/productos-vacuna', [VacunacionController::class, 'productosVacuna'])
                ->name('vacunaciones.productos-vacuna');
            Route::middleware('permission:vacunaciones.view')
                ->get('vacunaciones', [VacunacionController::class, 'index'])
                ->name('vacunaciones.index');
            Route::middleware('permission:vacunaciones.view')
                ->get('vacunaciones/{vacuna_aplicada}/pdf', [VacunacionController::class, 'aplicacionPdf'])
                ->name('vacunaciones.aplicacion-pdf');
            Route::middleware('permission:vacunaciones.create')
                ->post('vacunaciones', [VacunacionController::class, 'store'])
                ->name('vacunaciones.store');
            Route::middleware('permission:vacunaciones.update')
                ->match(['put', 'patch'], 'vacunaciones/{vacuna_aplicada}', [VacunacionController::class, 'update'])
                ->name('vacunaciones.update');
            Route::middleware('permission:vacunaciones.delete')
                ->delete('vacunaciones/{vacuna_aplicada}', [VacunacionController::class, 'destroy'])
                ->name('vacunaciones.destroy');

            Route::middleware('permission:citas.view')
                ->get('citas/export', [CitaController::class, 'exportExcel'])
                ->name('citas.export');
            Route::middleware('permission:citas.view')
                ->get('citas', [CitaController::class, 'index'])
                ->name('citas.index');
            Route::middleware('permission:citas.create')
                ->post('citas', [CitaController::class, 'store'])
                ->name('citas.store');
            Route::middleware('permission:citas.update')
                ->match(['put', 'patch'], 'citas/{cita}/reprogramar', [CitaController::class, 'reschedule'])
                ->name('citas.reschedule');
            Route::middleware('permission:citas.update')
                ->match(['put', 'patch'], 'citas/{cita}', [CitaController::class, 'update'])
                ->name('citas.update');
            Route::middleware('permission:citas.delete')
                ->delete('citas/{cita}', [CitaController::class, 'destroy'])
                ->name('citas.destroy');
            Route::middleware('permission:citas.cancel')
                ->post('citas/{cita}/cancelar', [CitaController::class, 'cancelar'])
                ->name('citas.cancelar');

            Route::middleware('permission:recetas.view')
                ->get('recetas/productos-medicamento', [RecetaController::class, 'productosMedicamento'])
                ->name('recetas.productos-medicamento');
            Route::middleware('permission:recetas.view')
                ->get('recetas/{receta}/pdf', [RecetaController::class, 'pdf'])
                ->name('recetas.pdf');
            Route::middleware('permission:recetas.view')
                ->get('recetas', [RecetaController::class, 'index'])
                ->name('recetas.index');
            Route::middleware('permission:recetas.create')
                ->post('recetas', [RecetaController::class, 'store'])
                ->name('recetas.store');
            Route::middleware('permission:recetas.update')
                ->match(['put', 'patch'], 'recetas/{receta}', [RecetaController::class, 'update'])
                ->name('recetas.update');
            Route::middleware('permission:recetas.delete')
                ->delete('recetas/{receta}', [RecetaController::class, 'destroy'])
                ->name('recetas.destroy');

            Route::middleware('permission:laboratorio.view')
                ->get('laboratorio', [LaboratorioController::class, 'index'])
                ->name('laboratorio.index');
            Route::middleware('permission:laboratorio.create')
                ->post('laboratorio', [LaboratorioController::class, 'store'])
                ->name('laboratorio.store');
            Route::middleware('permission:laboratorio.update')
                ->match(['put', 'patch'], 'laboratorio/{pedido_laboratorio}', [LaboratorioController::class, 'update'])
                ->name('laboratorio.update');
            Route::middleware('permission:laboratorio.delete')
                ->delete('laboratorio/{pedido_laboratorio}', [LaboratorioController::class, 'destroy'])
                ->name('laboratorio.destroy');
            Route::middleware('permission:laboratorio.view')
                ->get('laboratorio/lineas/{linea}/resultado-archivo', [LaboratorioController::class, 'downloadResultadoArchivo'])
                ->name('laboratorio.lineas.resultado-archivo');

            Route::middleware('permission:cirugias.view')
                ->get('cirugias', [CirugiaController::class, 'index'])
                ->name('cirugias.index');
            Route::middleware('permission:cirugias.create')
                ->post('cirugias', [CirugiaController::class, 'store'])
                ->name('cirugias.store');
            Route::middleware('permission:cirugias.update')
                ->match(['put', 'patch'], 'cirugias/{cirugia}', [CirugiaController::class, 'update'])
                ->name('cirugias.update');
            Route::middleware('permission:cirugias.delete')
                ->delete('cirugias/{cirugia}', [CirugiaController::class, 'destroy'])
                ->name('cirugias.destroy');

            Route::middleware('permission:hospitalizacion.view')
                ->get('hospitalizacion', [HospitalizacionController::class, 'index'])
                ->name('hospitalizacion.index');
            Route::middleware('permission:hospitalizacion.view')
                ->get('hospitalizacion/{internamiento}', [HospitalizacionController::class, 'show'])
                ->name('hospitalizacion.show');
            Route::middleware('permission:hospitalizacion.create')
                ->post('hospitalizacion', [HospitalizacionController::class, 'store'])
                ->name('hospitalizacion.store');
            Route::middleware('permission:hospitalizacion.update')
                ->match(['put', 'patch'], 'hospitalizacion/{internamiento}', [HospitalizacionController::class, 'update'])
                ->name('hospitalizacion.update');
            Route::middleware('permission:hospitalizacion.delete')
                ->delete('hospitalizacion/{internamiento}', [HospitalizacionController::class, 'destroy'])
                ->name('hospitalizacion.destroy');
            Route::middleware('permission:hospitalizacion.update')
                ->post('hospitalizacion/{internamiento}/evoluciones', [HospitalizacionController::class, 'storeEvolucion'])
                ->name('hospitalizacion.evoluciones.store');
            Route::middleware('permission:hospitalizacion.update')
                ->match(['put', 'patch'], 'hospitalizacion/{internamiento}/evoluciones/{evolucion}', [HospitalizacionController::class, 'updateEvolucion'])
                ->name('hospitalizacion.evoluciones.update');
            Route::middleware('permission:hospitalizacion.update')
                ->delete('hospitalizacion/{internamiento}/evoluciones/{evolucion}', [HospitalizacionController::class, 'destroyEvolucion'])
                ->name('hospitalizacion.evoluciones.destroy');

            Route::middleware('permission:consulta-cargos.view|hospitalizacion.view')
                ->get('hospitalizacion/{internamiento}/cargos', [InternamientoCargoController::class, 'show'])
                ->name('hospitalizacion.cargos.show');
            Route::middleware('permission:consulta-cargos.view|consulta-cargos.manage|productos.view|hospitalizacion.view')
                ->get('hospitalizacion/{internamiento}/cargos/productos-buscar', [InternamientoCargoController::class, 'productosBuscar'])
                ->name('hospitalizacion.cargos.productos-buscar');
            Route::middleware('permission:consulta-cargos.view|historias-clinicas.view')
                ->get('hospitalizacion/{internamiento}/cargos/ticket', [InternamientoCargoController::class, 'ticket'])
                ->name('hospitalizacion.cargos.ticket');
            Route::middleware('permission:consulta-cargos.manage|hospitalizacion.update')
                ->match(['put', 'patch'], 'hospitalizacion/{internamiento}/cargos', [InternamientoCargoController::class, 'update'])
                ->name('hospitalizacion.cargos.update');
            Route::middleware('permission:consulta-cargos.manage|hospitalizacion.update')
                ->post('hospitalizacion/{internamiento}/cargos/confirmar', [InternamientoCargoController::class, 'confirmar'])
                ->name('hospitalizacion.cargos.confirmar');
        });
    });

    // ===== Servicios (datos tenant) =====
    Route::prefix('servicios')->name('servicios.')->group(function () {
        Route::middleware('tenant.required')->group(function () {
            Route::middleware('permission:grooming.view')
                ->get('grooming', [GroomingTurnoController::class, 'index'])
                ->name('grooming');
            Route::middleware('permission:grooming.create')
                ->post('grooming', [GroomingTurnoController::class, 'store'])
                ->name('grooming.store');
            Route::middleware('permission:grooming.update')
                ->match(['put', 'patch'], 'grooming/{grooming_turno}', [GroomingTurnoController::class, 'update'])
                ->name('grooming.update');
            Route::middleware('permission:grooming.update')
                ->post('grooming/{grooming_turno}/estado', [GroomingTurnoController::class, 'cambiarEstado'])
                ->whereUuid('grooming_turno')
                ->name('grooming.estado');
            Route::middleware('permission:grooming.update')
                ->post('grooming/{grooming_turno}/fotos', [GroomingTurnoController::class, 'storeFoto'])
                ->whereUuid('grooming_turno')
                ->name('grooming.fotos.store');
            Route::middleware('permission:grooming.update')
                ->delete('grooming/{grooming_turno}/fotos/{foto}', [GroomingTurnoController::class, 'destroyFoto'])
                ->whereUuid(['grooming_turno', 'foto'])
                ->name('grooming.fotos.destroy');
            Route::middleware('permission:grooming.update')
                ->post('grooming/{grooming_turno}/whatsapp-fotos', [GroomingTurnoController::class, 'enviarWhatsApp'])
                ->whereUuid('grooming_turno')
                ->name('grooming.whatsapp');
            Route::middleware('permission:grooming.delete')
                ->delete('grooming/{grooming_turno}', [GroomingTurnoController::class, 'destroy'])
                ->name('grooming.destroy');
            Route::middleware('permission:grooming.create')
                ->post('grooming/servicios', [GroomingServicioController::class, 'store'])
                ->name('grooming.servicios.store');
            Route::middleware('permission:grooming.update')
                ->put('grooming/servicios/{groomingServicio}', [GroomingServicioController::class, 'update'])
                ->whereUuid('groomingServicio')
                ->name('grooming.servicios.update');
            Route::middleware('permission:grooming.delete')
                ->delete('grooming/servicios/{groomingServicio}', [GroomingServicioController::class, 'destroy'])
                ->whereUuid('groomingServicio')
                ->name('grooming.servicios.destroy');
            Route::middleware(['permission:hotel.view', 'tenant.module:hotel'])
                ->get('hotel', [HotelEstanciaController::class, 'index'])
                ->name('hotel');
            Route::middleware(['permission:hotel.create', 'tenant.module:hotel'])
                ->post('hotel', [HotelEstanciaController::class, 'store'])
                ->name('hotel.store');
            Route::middleware(['permission:hotel.update', 'tenant.module:hotel'])
                ->match(['put', 'patch'], 'hotel/{hotel_estancia}', [HotelEstanciaController::class, 'update'])
                ->name('hotel.update');
            Route::middleware(['permission:hotel.delete', 'tenant.module:hotel'])
                ->delete('hotel/{hotel_estancia}', [HotelEstanciaController::class, 'destroy'])
                ->name('hotel.destroy');
            Route::middleware(['permission:hotel.create', 'tenant.module:hotel'])
                ->post('hotel/tipos', [HotelTipoEstanciaController::class, 'store'])
                ->name('hotel.tipos.store');
            Route::middleware(['permission:hotel.update', 'tenant.module:hotel'])
                ->put('hotel/tipos/{hotelTipoEstancia}', [HotelTipoEstanciaController::class, 'update'])
                ->whereUuid('hotelTipoEstancia')
                ->name('hotel.tipos.update');
            Route::middleware(['permission:hotel.delete', 'tenant.module:hotel'])
                ->delete('hotel/tipos/{hotelTipoEstancia}', [HotelTipoEstanciaController::class, 'destroy'])
                ->whereUuid('hotelTipoEstancia')
                ->name('hotel.tipos.destroy');
            Route::middleware(['permission:hotel.view', 'tenant.module:hotel'])
                ->get('hotel/{hotel_estancia}/diarios', [HotelEstanciaController::class, 'diariosIndex'])
                ->name('hotel.diarios.index');
            Route::middleware(['permission:hotel.update', 'tenant.module:hotel'])
                ->post('hotel/{hotel_estancia}/diarios', [HotelEstanciaController::class, 'diariosStore'])
                ->name('hotel.diarios.store');
            Route::middleware(['permission:hotel.update', 'tenant.module:hotel'])
                ->delete('hotel/{hotel_estancia}/diarios/{hotel_estancia_diario}', [HotelEstanciaController::class, 'diariosDestroy'])
                ->name('hotel.diarios.destroy');
        });
    });

    // ===== Inventario =====
    Route::prefix('inventario')->name('inventario.')->group(function () {
        Route::middleware('permission:productos.view')
            ->get('productos', [ProductoInventarioController::class, 'index'])
            ->name('productos.index');
        Route::middleware('permission:productos.view')
            ->get('productos/export', [ProductoInventarioController::class, 'exportExcel'])
            ->name('productos.export');
        Route::middleware('permission:productos.create')
            ->post('productos', [ProductoInventarioController::class, 'store'])
            ->name('productos.store');
        Route::middleware('permission:productos.create')
            ->get('productos/plantilla-importacion', [ProductoInventarioController::class, 'downloadImportTemplate'])
            ->name('productos.import-template');
        Route::middleware('permission:productos.create')
            ->post('productos/importar', [ProductoInventarioController::class, 'importExcel'])
            ->name('productos.import');
        Route::middleware('permission:productos.create')
            ->post('productos/quick', [ProductoInventarioController::class, 'storeQuick'])
            ->name('productos.quick');
        Route::middleware('permission:productos.update')
            ->match(['put', 'patch'], 'productos/{producto}', [ProductoInventarioController::class, 'update'])
            ->name('productos.update');
        Route::middleware('permission:productos.delete')
            ->delete('productos/{producto}', [ProductoInventarioController::class, 'destroy'])
            ->name('productos.destroy');
        Route::middleware('permission:productos.update|productos.create')
            ->post('unidades-medida', [UnidadMedidaInventarioController::class, 'store'])
            ->name('unidades-medida.store');
        Route::middleware('permission:productos.update')
            ->patch('unidades-medida/{unidadMedida}', [UnidadMedidaInventarioController::class, 'update'])
            ->name('unidades-medida.update');
        Route::middleware('permission:productos.update')
            ->delete('unidades-medida/{unidadMedida}', [UnidadMedidaInventarioController::class, 'destroy'])
            ->name('unidades-medida.destroy');
        Route::middleware('permission:categorias-inventario.view')
            ->get('categorias', [CategoriaInventarioController::class, 'index'])
            ->name('categorias.index');
        Route::middleware('permission:categorias-inventario.create')
            ->post('categorias', [CategoriaInventarioController::class, 'store'])
            ->name('categorias.store');
        Route::middleware('permission:categorias-inventario.update')
            ->match(['put', 'patch'], 'categorias/{categoria}', [CategoriaInventarioController::class, 'update'])
            ->name('categorias.update');
        Route::middleware('permission:categorias-inventario.delete')
            ->delete('categorias/{categoria}', [CategoriaInventarioController::class, 'destroy'])
            ->name('categorias.destroy');
        Route::middleware('permission:stock.view')
            ->get('stock', [StockInventarioController::class, 'index'])
            ->name('stock');
        Route::middleware('permission:stock.view')
            ->get('stock/export', [StockInventarioController::class, 'exportExcel'])
            ->name('stock.export');
        Route::middleware('permission:stock.adjust')
            ->get('stock/plantilla-importacion', [StockInventarioController::class, 'downloadImportTemplate'])
            ->name('stock.import-template');
        Route::middleware('permission:stock.adjust')
            ->post('stock/importar', [StockInventarioController::class, 'importExcel'])
            ->name('stock.import');
        Route::middleware('permission:stock.adjust')
            ->patch('stock', [StockInventarioController::class, 'adjust'])
            ->name('stock.adjust');
        Route::middleware('permission:movimientos-stock.export')
            ->get('movimientos/export', [MovimientoInventarioController::class, 'exportExcel'])
            ->name('movimientos.export');
        Route::middleware('permission:movimientos-stock.view')
            ->get('movimientos', [MovimientoInventarioController::class, 'index'])
            ->name('movimientos');
        Route::middleware('permission:movimientos-stock.create')
            ->post('movimientos', [MovimientoInventarioController::class, 'store'])
            ->name('movimientos.store');
        Route::middleware('permission:alertas-stock.view')
            ->get('alertas', [AlertaStockInventarioController::class, 'alertas'])
            ->name('alertas');
        Route::middleware('permission:proveedores.create|proveedores.update')
            ->middleware('throttle:20,1')
            ->get('proveedores/consulta-ruc', [ProveedorInventarioController::class, 'consultaRuc'])
            ->name('proveedores.consulta-ruc');
        Route::middleware('permission:proveedores.view')
            ->get('proveedores', [ProveedorInventarioController::class, 'index'])
            ->name('proveedores.index');
        Route::middleware('permission:proveedores.create')
            ->post('proveedores', [ProveedorInventarioController::class, 'store'])
            ->name('proveedores.store');
        Route::middleware('permission:proveedores.update')
            ->match(['put', 'patch'], 'proveedores/{proveedor}', [ProveedorInventarioController::class, 'update'])
            ->name('proveedores.update');
        Route::middleware('permission:proveedores.delete')
            ->delete('proveedores/{proveedor}', [ProveedorInventarioController::class, 'destroy'])
            ->name('proveedores.destroy');
        Route::middleware('permission:compras.view')
            ->get('compras/export', [CompraInventarioController::class, 'exportExcel'])
            ->name('compras.export');
        Route::middleware('permission:compras.view')
            ->get('compras', [CompraInventarioController::class, 'index'])
            ->name('compras.index');
        Route::middleware('permission:compras.create')
            ->post('compras', [CompraInventarioController::class, 'store'])
            ->name('compras.store');
        Route::middleware('permission:compras.view')
            ->get('compras/{compra}/factura', [CompraInventarioController::class, 'downloadFactura'])
            ->name('compras.factura');
        Route::middleware('permission:compras.delete')
            ->delete('compras/{compra}', [CompraInventarioController::class, 'destroy'])
            ->name('compras.destroy');
    });

    // ===== Caja =====
    Route::prefix('caja')->name('caja.')->group(function () {
        Route::middleware('permission:caja-sesiones.view')
            ->get('sesiones', [CajaSesionController::class, 'index'])
            ->name('sesiones.index');
        Route::middleware('permission:caja-sesiones.open')
            ->post('sesiones', [CajaSesionController::class, 'store'])
            ->name('sesiones.store');
        Route::middleware('permission:caja-sesiones.close')
            ->post('sesiones/{caja_sesion}/cerrar', [CajaSesionController::class, 'cerrar'])
            ->name('sesiones.cerrar');
        Route::middleware('permission:ventas.view')
            ->get('ventas/export', [VentaController::class, 'exportExcel'])
            ->name('ventas.export');
        Route::middleware('permission:ventas.view')
            ->get('ventas', [VentaController::class, 'index'])
            ->name('ventas.index');
        Route::middleware('permission:ventas.create')
            ->get('ventas/nuevo', [VentaController::class, 'create'])
            ->name('ventas.create');
        Route::middleware(['permission:ventas.create', 'permission:consulta-cargos.cobrar'])
            ->get('ventas/desde-consulta/{consulta}', [VentaController::class, 'createDesdeConsulta'])
            ->name('ventas.create-desde-consulta');
        Route::middleware(['permission:ventas.create', 'permission:consulta-cargos.cobrar'])
            ->get('ventas/desde-internamiento/{internamiento}', [VentaController::class, 'createDesdeInternamiento'])
            ->name('ventas.create-desde-internamiento');
        Route::middleware(['permission:ventas.create', 'permission:consulta-cargos.cobrar'])
            ->get('ventas/desde-grooming/{grooming_turno}', [VentaController::class, 'createDesdeGrooming'])
            ->name('ventas.create-desde-grooming');
        Route::middleware(['permission:ventas.create', 'permission:hotel.view', 'tenant.module:hotel'])
            ->get('ventas/desde-hotel/{hotel_estancia}', [VentaController::class, 'createDesdeHotel'])
            ->name('ventas.create-desde-hotel');
        Route::middleware('permission:ventas.create')
            ->post('ventas', [VentaController::class, 'store'])
            ->name('ventas.store');
        Route::middleware('permission:ventas.create')
            ->post('ventas/propietarios-rapido', [VentaController::class, 'storePropietarioRapido'])
            ->name('ventas.propietarios-rapido');
        Route::middleware(['permission:ventas.create', 'permission:productos.create'])
            ->post('ventas/productos-rapido', [VentaController::class, 'storeProductoRapido'])
            ->name('ventas.productos-rapido');
        Route::middleware('permission:ventas.create')
            ->post('ventas/servicios-rapido', [VentaController::class, 'storeServicioRapido'])
            ->name('ventas.servicios-rapido');
        Route::middleware('permission:ventas.create')
            ->get('ventas/pacientes-por-propietario', [VentaController::class, 'pacientesPorPropietario'])
            ->name('ventas.pacientes-por-propietario');
        Route::middleware('permission:ventas.create')
            ->get('ventas/buscar-productos', [VentaController::class, 'buscarProductos'])
            ->name('ventas.buscar-productos');
        Route::middleware('permission:ventas.create')
            ->get('ventas/buscar-servicios', [VentaController::class, 'buscarServiciosTarifa'])
            ->name('ventas.buscar-servicios');

        Route::middleware('permission:ventas.view')
            ->get('ventas/{venta}/ticket', [VentaController::class, 'ticket'])
            ->whereUuid('venta')
            ->name('ventas.ticket');
        Route::middleware('permission:ventas.view')
            ->post('ventas/{venta}/enviar-whatsapp', [VentaController::class, 'enviarWhatsApp'])
            ->whereUuid('venta')
            ->name('ventas.enviar-whatsapp');
        Route::middleware('permission:ventas.create')
            ->post('ventas/{venta}/emitir-fel', [VentaController::class, 'emitirFel'])
            ->whereUuid('venta')
            ->name('ventas.emitir-fel');
        Route::middleware('permission:ventas.delete')
            ->post('ventas/{venta}/anular', [VentaController::class, 'anular'])
            ->whereUuid('venta')
            ->name('ventas.anular');
        Route::middleware('permission:ventas.view')
            ->get('ventas/{venta}', [VentaController::class, 'show'])
            ->whereUuid('venta')
            ->name('ventas.show');
        Route::middleware('permission:ventas.create')
            ->post('ventas/preview-promotions', [PromotionController::class, 'preview'])
            ->name('ventas.preview-promotions');
        Route::middleware('permission:descuentos.view')
            ->get('descuentos', [PromotionController::class, 'index'])
            ->name('descuentos.index');
        Route::middleware('permission:descuentos.create')
            ->post('descuentos', [PromotionController::class, 'store'])
            ->name('descuentos.store');
        Route::middleware('permission:descuentos.update')
            ->put('descuentos/{promotion}', [PromotionController::class, 'update'])
            ->whereUuid('promotion')
            ->name('descuentos.update');
        Route::middleware('permission:descuentos.delete')
            ->delete('descuentos/{promotion}', [PromotionController::class, 'destroy'])
            ->whereUuid('promotion')
            ->name('descuentos.destroy');
        Route::inertia('pagos', 'caja/pagos/index')->name('pagos');
    });

    // ===== Facturación =====
    Route::prefix('facturacion')->name('facturacion.')->group(function () {
        Route::middleware('permission:documentos.view')
            ->get('documentos', [FelDocumentController::class, 'index'])
            ->name('documentos');
        Route::middleware('permission:documentos.view')
            ->get('documentos/{felDocument}/download-xml', [FelDocumentController::class, 'downloadXml'])
            ->whereUuid('felDocument')
            ->name('documentos.download-xml');
        Route::middleware('permission:documentos.view')
            ->get('documentos/{felDocument}/download-cdr', [FelDocumentController::class, 'downloadCdr'])
            ->whereUuid('felDocument')
            ->name('documentos.download-cdr');
        Route::middleware('permission:documentos.view')
            ->get('documentos/{felDocument}/json', [FelDocumentController::class, 'json'])
            ->whereUuid('felDocument')
            ->name('documentos.json');
        Route::middleware('permission:documentos.send')
            ->post('documentos/{felDocument}/enviar-whatsapp', [FelDocumentController::class, 'enviarWhatsApp'])
            ->whereUuid('felDocument')
            ->name('documentos.enviar-whatsapp');

        // Series de comprobantes
        Route::middleware('permission:series.view')
            ->get('series', [FelSerieController::class, 'index'])->name('series');
        Route::middleware('permission:series.create')
            ->post('series', [FelSerieController::class, 'store'])->name('series.store');
        Route::middleware('permission:series.update')
            ->patch('series/{felSerie}', [FelSerieController::class, 'update'])->name('series.update');
        Route::middleware('permission:series.delete')
            ->delete('series/{felSerie}', [FelSerieController::class, 'destroy'])->name('series.destroy');

        Route::inertia('notas-baja', 'facturacion/notas-baja/index')->name('notas-baja');
        Route::inertia('resumenes', 'facturacion/resumenes/index')->name('resumenes');
    });

    // ===== Comunicaciones (schema del tenant: cola, WhatsApp por clínica) =====
    Route::prefix('comunicaciones')->name('comunicaciones.')->middleware('tenant.required')->group(function () {
        Route::middleware('permission:comunicaciones-cola.view')
            ->get('cola', [NotificationQueueController::class, 'cola'])
            ->name('cola');
        Route::middleware('permission:comunicaciones-historico.view')
            ->get('historico', [NotificationQueueController::class, 'historico'])
            ->name('historico');
        Route::middleware('permission:comunicaciones-bot-ia.view|config-general.view|comunicaciones-cola.manage|comunicaciones-cola.view|comunicaciones-historico.view')
            ->get('bot-ia', [ClinicBotIaController::class, 'show'])
            ->name('bot-ia');

        Route::middleware('permission:comunicaciones-bot-ia.manage|config-general.update|comunicaciones-cola.manage')
            ->prefix('bot-ia')
            ->name('bot-ia.')
            ->group(function (): void {
                Route::prefix('conocimiento')->name('knowledge.')->group(function (): void {
                    Route::post('/', [ClinicBotIaController::class, 'storeKnowledge'])->name('store');
                    Route::put('{clinicBotKnowledge}', [ClinicBotIaController::class, 'updateKnowledge'])->name('update');
                    Route::delete('{clinicBotKnowledge}', [ClinicBotIaController::class, 'destroyKnowledge'])->name('destroy');
                });

                Route::post('conversaciones/{clinicBotConversation}/pause', [ClinicBotIaController::class, 'pauseConversation'])
                    ->name('conversations.pause');
                Route::post('conversaciones/{clinicBotConversation}/resume', [ClinicBotIaController::class, 'resumeConversation'])
                    ->name('conversations.resume');
                Route::post('asistente/toggle', [ClinicBotIaController::class, 'toggleAssistant'])
                    ->name('assistant.toggle');
            });
        Route::inertia('plantillas', 'comunicaciones/plantillas/index')->name('plantillas');

        Route::middleware('permission:comunicaciones-cola.manage')->group(function (): void {
            Route::post('cola/{notification}/cancel', [NotificationQueueController::class, 'cancel'])
                ->whereUuid('notification')
                ->name('cola.cancel');
            Route::post('cola/{notification}/retry', [NotificationQueueController::class, 'retry'])
                ->whereUuid('notification')
                ->name('cola.retry');
            Route::post('whatsapp/sync', [TenantWhatsAppController::class, 'sync'])->name('whatsapp.sync');
            Route::post('whatsapp/test', [TenantWhatsAppController::class, 'sendTest'])->name('whatsapp.test');
            Route::post('whatsapp/logout', [TenantWhatsAppController::class, 'logout'])->name('whatsapp.logout');
            Route::get('whatsapp/qr', [TenantWhatsAppController::class, 'qr'])->name('whatsapp.qr');
        });
    });

    // ===== Reportes =====
    Route::prefix('reportes')->name('reportes.')->group(function () {
        Route::inertia('snapshots', 'reportes/snapshots/index')->name('snapshots');
        Route::inertia('financiero', 'reportes/financiero/index')->name('financiero');
        Route::inertia('top-pacientes', 'reportes/top-pacientes/index')->name('top-pacientes');
    });

    // ===== Configuración =====
    Route::prefix('configuracion')->name('configuracion.')->group(function () {
        // General — singleton de configuración de la clínica.
        // Vive en `cfg_clinic_settings` dentro del schema del tenant,
        // por eso lleva `tenant.required`: en el host central (sin
        // tenant resuelto) un superadmin ve una pantalla informativa
        // que lo invita a entrar al panel de Plataforma › Tenants
        // (defensa en profundidad + UX); cualquier otro rol recibe 404.
        Route::middleware(['tenant.required', 'permission:config-general.view'])
            ->get('general', [ClinicSettingController::class, 'show'])
            ->name('general.show');
        Route::middleware(['tenant.required', 'permission:config-general.update'])
            ->match(['put', 'patch'], 'general', [ClinicSettingController::class, 'update'])
            ->name('general.update');

        Route::middleware(['tenant.required', 'permission:config-general.view'])
            ->get('suscripcion', [ClinicSubscriptionController::class, 'show'])
            ->name('suscripcion.show');

        Route::inertia('ayuda', 'configuracion/ayuda/index')->name('ayuda');

        // Sedes — CRUD real. Cada verbo HTTP exige su permiso específico.
        Route::middleware('permission:sedes.view')
            ->get('sedes', [SedeController::class, 'index'])
            ->name('sedes.index');
        Route::middleware('permission:sedes.export')
            ->get('sedes/export', [SedeController::class, 'export'])
            ->name('sedes.export');
        Route::middleware('permission:sedes.create')
            ->post('sedes', [SedeController::class, 'store'])
            ->name('sedes.store');
        Route::middleware('permission:sedes.bulk-delete')
            ->delete('sedes/bulk', [SedeController::class, 'bulkDestroy'])
            ->name('sedes.bulk-destroy');
        Route::middleware('permission:sedes.update')
            ->match(['put', 'patch'], 'sedes/{sede}', [SedeController::class, 'update'])
            ->name('sedes.update');
        Route::middleware('permission:sedes.delete')
            ->delete('sedes/{sede}', [SedeController::class, 'destroy'])
            ->name('sedes.destroy');

        // Roles & Permisos — CRUD real. Cada verbo HTTP exige su permiso específico.
        Route::middleware('permission:roles.view')
            ->get('roles', [RoleController::class, 'index'])
            ->name('roles.index');
        Route::middleware('permission:roles.export')
            ->get('roles/export', [RoleController::class, 'export'])
            ->name('roles.export');
        Route::middleware('permission:roles.create')
            ->post('roles', [RoleController::class, 'store'])
            ->name('roles.store');
        Route::middleware('permission:roles.bulk-delete')
            ->delete('roles/bulk', [RoleController::class, 'bulkDestroy'])
            ->name('roles.bulk-destroy');
        Route::middleware('permission:roles.update')
            ->put('roles/{role}/permissions', [RoleController::class, 'updatePermissions'])
            ->name('roles.update-permissions');
        Route::middleware('permission:roles.update')
            ->match(['put', 'patch'], 'roles/{role}', [RoleController::class, 'update'])
            ->name('roles.update');
        Route::middleware('permission:roles.delete')
            ->delete('roles/{role}', [RoleController::class, 'destroy'])
            ->name('roles.destroy');

        Route::inertia('horarios', 'configuracion/horarios/index')->name('horarios');
        Route::inertia('bloqueos', 'configuracion/bloqueos/index')->name('bloqueos');

        Route::middleware('permission:tarifas.view')
            ->get('tarifas', [TarifaServiciosController::class, 'index'])
            ->name('tarifas.index');
        Route::middleware('permission:tarifas.create')
            ->post('tarifas/grooming', [TarifaServiciosController::class, 'storeGrooming'])
            ->name('tarifas.grooming.store');
        Route::middleware('permission:tarifas.update')
            ->put('tarifas/grooming/{grooming_tarifa}', [TarifaServiciosController::class, 'updateGrooming'])
            ->whereUuid('grooming_tarifa')
            ->name('tarifas.grooming.update');
        Route::middleware('permission:tarifas.delete')
            ->delete('tarifas/grooming/{grooming_tarifa}', [TarifaServiciosController::class, 'destroyGrooming'])
            ->whereUuid('grooming_tarifa')
            ->name('tarifas.grooming.destroy');
        Route::middleware(['permission:tarifas.create', 'tenant.module:hotel'])
            ->post('tarifas/hotel', [TarifaServiciosController::class, 'storeHotel'])
            ->name('tarifas.hotel.store');
        Route::middleware(['permission:tarifas.update', 'tenant.module:hotel'])
            ->put('tarifas/hotel/{hotel_tarifa}', [TarifaServiciosController::class, 'updateHotel'])
            ->whereUuid('hotel_tarifa')
            ->name('tarifas.hotel.update');
        Route::middleware(['permission:tarifas.delete', 'tenant.module:hotel'])
            ->delete('tarifas/hotel/{hotel_tarifa}', [TarifaServiciosController::class, 'destroyHotel'])
            ->whereUuid('hotel_tarifa')
            ->name('tarifas.hotel.destroy');

        Route::middleware('permission:tarifas.create')
            ->post('tarifas/clinica', [TarifaServiciosController::class, 'storeClinica'])
            ->name('tarifas.clinica.store');
        Route::middleware('permission:tarifas.update')
            ->put('tarifas/clinica/{servicioClinico}', [TarifaServiciosController::class, 'updateClinica'])
            ->whereUuid('servicioClinico')
            ->name('tarifas.clinica.update');
        Route::middleware('permission:tarifas.delete')
            ->delete('tarifas/clinica/{servicioClinico}', [TarifaServiciosController::class, 'destroyClinica'])
            ->whereUuid('servicioClinico')
            ->name('tarifas.clinica.destroy');
        Route::middleware('permission:tarifas.create|tarifas.update')
            ->post('tarifas/clinica/categorias', [TarifaServiciosController::class, 'storeCategoriaClinica'])
            ->name('tarifas.clinica.categorias.store');
        Route::middleware('permission:tarifas.create|tarifas.update')
            ->post('tarifas/grooming/categorias', [TarifaServiciosController::class, 'storeCategoriaGrooming'])
            ->name('tarifas.grooming.categorias.store');
        Route::middleware('permission:tarifas.create|tarifas.update')
            ->post('tarifas/hotel/categorias', [TarifaServiciosController::class, 'storeCategoriaHotel'])
            ->name('tarifas.hotel.categorias.store');

        Route::middleware('permission:tarifas.create')
            ->post('tarifas/grooming/servicios', [GroomingServicioController::class, 'store'])
            ->name('tarifas.grooming.servicios.store');
        Route::middleware('permission:tarifas.update')
            ->put('tarifas/grooming/servicios/{groomingServicio}', [GroomingServicioController::class, 'update'])
            ->whereUuid('groomingServicio')
            ->name('tarifas.grooming.servicios.update');
        Route::middleware('permission:tarifas.delete')
            ->delete('tarifas/grooming/servicios/{groomingServicio}', [GroomingServicioController::class, 'destroy'])
            ->whereUuid('groomingServicio')
            ->name('tarifas.grooming.servicios.destroy');

        Route::middleware('permission:tarifas.view')
            ->get('tarifas/grooming/servicios/{groomingServicio}/insumos', [GroomingInsumoController::class, 'index'])
            ->whereUuid('groomingServicio')
            ->name('tarifas.grooming.servicios.insumos.index');
        Route::middleware('permission:tarifas.update')
            ->put('tarifas/grooming/servicios/{groomingServicio}/insumos', [GroomingInsumoController::class, 'sync'])
            ->whereUuid('groomingServicio')
            ->name('tarifas.grooming.servicios.insumos.sync');
        Route::middleware(['permission:tarifas.create', 'tenant.module:hotel'])
            ->post('tarifas/hotel/tipos', [HotelTipoEstanciaController::class, 'store'])
            ->name('tarifas.hotel.tipos.store');
        Route::middleware(['permission:tarifas.update', 'tenant.module:hotel'])
            ->put('tarifas/hotel/tipos/{hotelTipoEstancia}', [HotelTipoEstanciaController::class, 'update'])
            ->whereUuid('hotelTipoEstancia')
            ->name('tarifas.hotel.tipos.update');
        Route::middleware(['permission:tarifas.delete', 'tenant.module:hotel'])
            ->delete('tarifas/hotel/tipos/{hotelTipoEstancia}', [HotelTipoEstanciaController::class, 'destroy'])
            ->whereUuid('hotelTipoEstancia')
            ->name('tarifas.hotel.tipos.destroy');

        // Usuarios — CRUD real. Cada verbo HTTP exige su permiso específico.
        Route::middleware('permission:usuarios.view')
            ->get('usuarios', [UserController::class, 'index'])
            ->name('usuarios.index');
        Route::middleware('permission:usuarios.export')
            ->get('usuarios/export', [UserController::class, 'export'])
            ->name('usuarios.export');
        Route::middleware('permission:usuarios.create')
            ->post('usuarios', [UserController::class, 'store'])
            ->name('usuarios.store');
        Route::middleware('permission:usuarios.bulk-delete')
            ->delete('usuarios/bulk', [UserController::class, 'bulkDestroy'])
            ->name('usuarios.bulk-destroy');
        Route::middleware('permission:usuarios.update')
            ->match(['put', 'patch'], 'usuarios/{user}', [UserController::class, 'update'])
            ->name('usuarios.update');
        Route::middleware('permission:usuarios.delete')
            ->delete('usuarios/{user}', [UserController::class, 'destroy'])
            ->name('usuarios.destroy');
    });

    // ===== Auditoría =====
    Route::prefix('auditoria')->name('auditoria.')->group(function () {
        Route::middleware(['permission:auditoria-logs.view', 'tenant.module:auditoria_logs'])
            ->get('logs', [AuditLogController::class, 'index'])
            ->name('logs');
        Route::middleware(['permission:auditoria-logs.export', 'tenant.module:auditoria_logs'])
            ->get('logs/export', [AuditLogController::class, 'export'])
            ->name('logs.export');
        Route::inertia('login-attempts', 'auditoria/login-attempts/index')->name('login-attempts');
        Route::inertia('api-logs', 'auditoria/api-logs/index')->name('api-logs');
        Route::inertia('tokens', 'auditoria/tokens/index')->name('tokens');
    });

    /*
    |--------------------------------------------------------------------------
    | Plataforma SaaS — administración interna
    |--------------------------------------------------------------------------
    | Pensado para el superadmin que opera el negocio VetSaaS:
    | tenants, planes, suscripciones, etc.
    |
    | El sidebar oculta este grupo si el usuario no tiene ningún permiso
    | `plataforma-*`, así que estos endpoints quedan solo expuestos al
    | superadmin (y, a futuro, a roles de soporte interno).
    */
    Route::prefix('plataforma')->name('plataforma.')->group(function () {
        // ── Operaciones (radar de salud del SaaS) ──
        Route::middleware('permission:plataforma-operaciones.view')
            ->get('operaciones', [PlataformaOperacionesController::class, 'index'])
            ->name('operaciones.index');
        Route::middleware('permission:plataforma-operaciones.manage')
            ->post('operaciones/failed-jobs/{uuid}/retry', [PlataformaOperacionesController::class, 'retryFailedJob'])
            ->whereUuid('uuid')
            ->name('operaciones.failed-jobs.retry');
        Route::middleware('permission:plataforma-operaciones.manage')
            ->post('operaciones/failed-jobs/retry-all', [PlataformaOperacionesController::class, 'retryAllFailedJobs'])
            ->name('operaciones.failed-jobs.retry-all');
        Route::middleware('permission:plataforma-operaciones.manage')
            ->post('operaciones/backups/run', [PlataformaOperacionesController::class, 'runBackup'])
            ->name('operaciones.backups.run');

        Route::middleware('permission:plataforma-tenants.view')
            ->get('tenants', [TenantController::class, 'index'])
            ->name('tenants.index');
        Route::middleware('permission:plataforma-tenants.export')
            ->get('tenants/export', [TenantController::class, 'export'])
            ->name('tenants.export');
        Route::middleware('permission:plataforma-tenants.create')
            ->post('tenants', [TenantController::class, 'store'])
            ->name('tenants.store');
        Route::middleware('permission:plataforma-tenants.bulk-delete')
            ->delete('tenants/bulk', [TenantController::class, 'bulkDestroy'])
            ->name('tenants.bulk-destroy');
        Route::middleware('permission:plataforma-tenants.suspend')
            ->post('tenants/{tenant}/suspend', [TenantController::class, 'suspend'])
            ->name('tenants.suspend');
        Route::middleware('permission:plataforma-tenants.resume')
            ->post('tenants/{tenant}/resume', [TenantController::class, 'resume'])
            ->name('tenants.resume');
        Route::middleware('permission:plataforma-tenants.update')
            ->post('tenants/{tenant}/change-slug', [TenantController::class, 'changeSlug'])
            ->name('tenants.change-slug');
        Route::middleware('permission:plataforma-tenants.impersonate')
            ->post('tenants/{tenant}/impersonate', [TenantImpersonationController::class, 'start'])
            ->name('tenants.impersonate');
        Route::middleware('permission:plataforma-tenants.whatsapp-restart')
            ->post('tenants/{tenant}/whatsapp/restart', [TenantWhatsAppPlatformController::class, 'restart'])
            ->name('tenants.whatsapp.restart');
        Route::middleware('permission:plataforma-tenants.whatsapp-stop')
            ->post('tenants/{tenant}/whatsapp/stop', [TenantWhatsAppPlatformController::class, 'stop'])
            ->name('tenants.whatsapp.stop');
        Route::middleware('permission:plataforma-tenants.view')
            ->get('auditoria-soporte', [PlataformaImpersonationAuditController::class, 'index'])
            ->name('auditoria-soporte.index');
        Route::middleware('permission:plataforma-tenants.update')
            ->get('tenants/{tenant}/modulos', [TenantModuleController::class, 'edit'])
            ->name('tenants.modules.edit');
        Route::middleware('permission:plataforma-tenants.update')
            ->put('tenants/{tenant}/modulos', [TenantModuleController::class, 'update'])
            ->name('tenants.modules.update');
        Route::middleware('permission:plataforma-tenants.update')
            ->match(['put', 'patch'], 'tenants/{tenant}', [TenantController::class, 'update'])
            ->name('tenants.update');
        Route::middleware('permission:plataforma-tenants.delete')
            ->delete('tenants/{tenant}', [TenantController::class, 'destroy'])
            ->name('tenants.destroy');

        // ── Planes (catálogo de productos del SaaS) ──
        Route::middleware('permission:plataforma-planes.view')
            ->get('planes', [PlanController::class, 'index'])
            ->name('planes.index');
        Route::middleware('permission:plataforma-planes.export')
            ->get('planes/export', [PlanController::class, 'export'])
            ->name('planes.export');
        Route::middleware('permission:plataforma-planes.create')
            ->post('planes', [PlanController::class, 'store'])
            ->name('planes.store');
        Route::middleware('permission:plataforma-planes.bulk-delete')
            ->delete('planes/bulk', [PlanController::class, 'bulkDestroy'])
            ->name('planes.bulk-destroy');
        Route::middleware('permission:plataforma-planes.update')
            ->put('planes/{plan}/features', [PlanController::class, 'updateFeatures'])
            ->name('planes.update-features');
        Route::middleware('permission:plataforma-planes.update')
            ->match(['put', 'patch'], 'planes/{plan}', [PlanController::class, 'update'])
            ->name('planes.update');
        Route::middleware('permission:plataforma-planes.delete')
            ->delete('planes/{plan}', [PlanController::class, 'destroy'])
            ->name('planes.destroy');

        // ── Suscripciones (panel de operación / cobranza interna) ──
        Route::middleware('permission:plataforma-suscripciones.view')
            ->get('suscripciones', [SubscriptionController::class, 'index'])
            ->name('suscripciones.index');
        Route::middleware('permission:plataforma-suscripciones.view')
            ->get('suscripciones/{suscripcion}/renewal-reminder-preview', [SubscriptionController::class, 'renewalReminderPreview'])
            ->name('suscripciones.renewal-reminder-preview');
        Route::middleware('permission:plataforma-suscripciones.update')
            ->post('suscripciones/{suscripcion}/send-renewal-whatsapp', [SubscriptionController::class, 'sendRenewalWhatsApp'])
            ->name('suscripciones.send-renewal-whatsapp');
        Route::middleware('permission:plataforma-suscripciones.export')
            ->get('suscripciones/export', [SubscriptionController::class, 'export'])
            ->name('suscripciones.export');
        Route::middleware('permission:plataforma-suscripciones.create')
            ->post('suscripciones', [SubscriptionController::class, 'store'])
            ->name('suscripciones.store');
        Route::middleware('permission:plataforma-suscripciones.bulk-delete')
            ->delete('suscripciones/bulk', [SubscriptionController::class, 'bulkDestroy'])
            ->name('suscripciones.bulk-destroy');
        Route::middleware('permission:plataforma-suscripciones.extend-trial')
            ->post('suscripciones/{suscripcion}/extend-trial', [SubscriptionController::class, 'extendTrial'])
            ->name('suscripciones.extend-trial');
        Route::middleware('permission:plataforma-suscripciones.change-plan')
            ->post('suscripciones/{suscripcion}/change-plan', [SubscriptionController::class, 'changePlan'])
            ->name('suscripciones.change-plan');
        Route::middleware('permission:plataforma-suscripciones.cancel')
            ->post('suscripciones/{suscripcion}/cancel', [SubscriptionController::class, 'cancel'])
            ->name('suscripciones.cancel');
        Route::middleware('permission:plataforma-suscripciones.update|plataforma-suscripciones.toggle-bot-ia')
            ->post('suscripciones/{suscripcion}/toggle-bot-ia', [SubscriptionController::class, 'toggleBotIa'])
            ->name('suscripciones.toggle-bot-ia');
        Route::middleware('permission:plataforma-suscripciones.update')
            ->match(['put', 'patch'], 'suscripciones/{suscripcion}', [SubscriptionController::class, 'update'])
            ->name('suscripciones.update');
        Route::middleware('permission:plataforma-suscripciones.delete')
            ->delete('suscripciones/{suscripcion}', [SubscriptionController::class, 'destroy'])
            ->name('suscripciones.destroy');

        // ── Bot de ventas: conversaciones (panel de control) ──
        // Rodrigo pausa/reactiva el bot por lead desde el navegador (celular ok).
        Route::middleware('permission:salesbot-knowledge.view')
            ->get('salesbot-conversations', [SalesBotConversationController::class, 'index'])
            ->name('salesbot-conversations.index');
        Route::middleware('permission:salesbot-knowledge.update')
            ->post('salesbot-conversations/{conversation}/pause', [SalesBotConversationController::class, 'pause'])
            ->name('salesbot-conversations.pause');
        Route::middleware('permission:salesbot-knowledge.update')
            ->post('salesbot-conversations/{conversation}/resume', [SalesBotConversationController::class, 'resume'])
            ->name('salesbot-conversations.resume');
        Route::middleware('permission:salesbot-knowledge.delete')
            ->delete('salesbot-conversations/{conversation}', [SalesBotConversationController::class, 'destroy'])
            ->name('salesbot-conversations.destroy');
        Route::middleware('permission:salesbot-knowledge.update')
            ->post('salesbot-conversations/{conversation}/convert', [SalesBotConversationController::class, 'convert'])
            ->name('salesbot-conversations.convert');
        Route::middleware('permission:salesbot-knowledge.update')
            ->post('salesbot-conversations/{conversation}/reactivate', [SalesBotConversationController::class, 'reactivate'])
            ->name('salesbot-conversations.reactivate');
        Route::middleware('permission:salesbot-knowledge.update')
            ->post('salesbot-conversations/{conversation}/engage', [SalesBotConversationController::class, 'engage'])
            ->name('salesbot-conversations.engage');
        Route::middleware('permission:salesbot-knowledge.update')
            ->post('salesbot-conversations/engage-phone', [SalesBotConversationController::class, 'engagePhone'])
            ->name('salesbot-conversations.engage-phone');
        Route::middleware('permission:salesbot-knowledge.view')
            ->get('salesbot-conversations/csv-template', [SalesBotConversationController::class, 'csvTemplate'])
            ->name('salesbot-conversations.csv-template');
        Route::middleware('permission:salesbot-knowledge.update')
            ->post('salesbot-conversations/import-csv', [SalesBotConversationController::class, 'importCsv'])
            ->name('salesbot-conversations.import-csv');

        // ── Bot de ventas: base de conocimiento (planes, módulos, FAQs) ──
        // Solo superadmin. Cualquier cambio invalida el caché del bot (5 min).
        Route::middleware('permission:salesbot-knowledge.view')
            ->get('salesbot-knowledge', [SalesBotKnowledgeController::class, 'index'])
            ->name('salesbot-knowledge.index');
        Route::middleware('permission:salesbot-knowledge.create')
            ->post('salesbot-knowledge', [SalesBotKnowledgeController::class, 'store'])
            ->name('salesbot-knowledge.store');
        Route::middleware('permission:salesbot-knowledge.update')
            ->match(['put', 'patch'], 'salesbot-knowledge/{salesbotKnowledge}', [SalesBotKnowledgeController::class, 'update'])
            ->name('salesbot-knowledge.update');
        Route::middleware('permission:salesbot-knowledge.delete')
            ->delete('salesbot-knowledge/{salesbotKnowledge}', [SalesBotKnowledgeController::class, 'destroy'])
            ->name('salesbot-knowledge.destroy');
        Route::middleware('permission:salesbot-knowledge.update')
            ->post('salesbot-knowledge/flush-cache', [SalesBotKnowledgeController::class, 'flushCache'])
            ->name('salesbot-knowledge.flush-cache');

        // ── Novedades in-app del Asistente IA (tenants) ──
        Route::middleware('permission:bot-ia-announcements.view')
            ->get('bot-ia-announcements', [BotIaAnnouncementController::class, 'index'])
            ->name('bot-ia-announcements.index');
        Route::middleware('permission:bot-ia-announcements.create')
            ->post('bot-ia-announcements', [BotIaAnnouncementController::class, 'store'])
            ->name('bot-ia-announcements.store');
        Route::middleware('permission:bot-ia-announcements.update')
            ->match(['put', 'patch'], 'bot-ia-announcements/{botIaAnnouncement}', [BotIaAnnouncementController::class, 'update'])
            ->name('bot-ia-announcements.update');
        Route::middleware('permission:bot-ia-announcements.delete')
            ->delete('bot-ia-announcements/{botIaAnnouncement}', [BotIaAnnouncementController::class, 'destroy'])
            ->name('bot-ia-announcements.destroy');
        Route::middleware('permission:bot-ia-announcements.update')
            ->post('bot-ia-announcements/{botIaAnnouncement}/activate', [BotIaAnnouncementController::class, 'activate'])
            ->name('bot-ia-announcements.activate');

        // ── Configuración global del SaaS (Twilio + Brevo) ──
        // Singleton en `public.platform_settings` con las credenciales
        // de los proveedores externos compartidos por todas las clínicas.
        // No requiere `tenant.required`: la configuración es global, no
        // por tenant. Solo `superadmin` tiene los permisos requeridos.
        Route::middleware('permission:platform-settings.view')
            ->get('configuracion', [PlatformSettingController::class, 'show'])
            ->name('configuracion.show');
        Route::middleware('permission:platform-settings.update')
            ->match(['put', 'patch'], 'configuracion', [PlatformSettingController::class, 'update'])
            ->name('configuracion.update');

        Route::middleware('permission:platform-settings.update')->group(function (): void {
            Route::post('configuracion/novedades', [InAppAssistantAnnouncementController::class, 'store'])
                ->name('configuracion.novedades.store');
            Route::match(['put', 'patch'], 'configuracion/novedades/{novedad}', [InAppAssistantAnnouncementController::class, 'update'])
                ->name('configuracion.novedades.update');
            Route::post('configuracion/novedades/{novedad}/republicar', [InAppAssistantAnnouncementController::class, 'republish'])
                ->name('configuracion.novedades.republish');
            Route::post('configuracion/novedades/{novedad}/activar', [InAppAssistantAnnouncementController::class, 'activate'])
                ->name('configuracion.novedades.activate');
            Route::delete('configuracion/novedades/{novedad}', [InAppAssistantAnnouncementController::class, 'destroy'])
                ->name('configuracion.novedades.destroy');
        });
        // ── Cobros / Pagos (read-only + soporte sobre subscription_payments) ──
        // Misma data: Orvae escribe los pagos; Cobros = operación (todos/pendientes/fallidos),
        // Pagos = quién ya pagó (estado procesado por defecto).
        Route::middleware('permission:plataforma-cobros.view')
            ->get('cobros', [SubscriptionPaymentController::class, 'index'])
            ->name('cobros.index');
        Route::middleware('permission:plataforma-cobros.view')
            ->get('pagos', [SubscriptionPaymentController::class, 'index'])
            ->name('pagos.index');
        Route::middleware('permission:plataforma-cobros.export')
            ->get('cobros/export', [SubscriptionPaymentController::class, 'export'])
            ->name('cobros.export');
        Route::middleware('permission:plataforma-cobros.refund')
            ->post('cobros/{cobro}/mark-refunded', [SubscriptionPaymentController::class, 'markRefunded'])
            ->name('cobros.mark-refunded');
        Route::middleware('permission:plataforma-cobros.add-note')
            ->post('cobros/{cobro}/note', [SubscriptionPaymentController::class, 'addNote'])
            ->name('cobros.add-note');
        Route::middleware('permission:plataforma-cobros.resend-invoice')
            ->post('cobros/{cobro}/resend-invoice', [SubscriptionPaymentController::class, 'resendInvoice'])
            ->name('cobros.resend-invoice');

        // ── Avisos de renovación (WhatsApp plataforma → tenants) ──
        Route::middleware('permission:plataforma-suscripciones.view')
            ->get('avisos-renovacion', [PlatformRenewalReminderController::class, 'index'])
            ->name('avisos-renovacion.index');
        Route::middleware('permission:plataforma-suscripciones.update')->group(function (): void {
            Route::post('avisos-renovacion/whatsapp/sync', [PlatformWhatsAppController::class, 'sync'])
                ->name('avisos-renovacion.whatsapp.sync');
            Route::get('avisos-renovacion/whatsapp/qr', [PlatformWhatsAppController::class, 'qr'])
                ->name('avisos-renovacion.whatsapp.qr');
            Route::post('avisos-renovacion/whatsapp/logout', [PlatformWhatsAppController::class, 'logout'])
                ->name('avisos-renovacion.whatsapp.logout');
            Route::post('avisos-renovacion/whatsapp/test', [PlatformWhatsAppController::class, 'sendTest'])
                ->name('avisos-renovacion.whatsapp.test');
        });
    });
});

require __DIR__.'/settings.php';
