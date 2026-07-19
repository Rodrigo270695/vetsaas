<?php

use App\Http\Controllers\ConsultaHistoriaController;
use App\Http\Controllers\LaboratorioController;
use App\Http\Controllers\PacienteController;
use App\Http\Controllers\Tenant\TenantDashboardController;
use App\Http\Controllers\VacunacionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas exclusivas del subdominio del tenant
|--------------------------------------------------------------------------
|
| Arquitectura "single-login + datos aislados": las rutas autenticadas
| (dashboard, módulos operativos, plataforma) son COMPARTIDAS con el
| panel central y viven en `routes/web.php`. El sidebar y la UI se
| filtran por permisos; el middleware `tenant.match-user` valida que el
| usuario pertenezca al host actual.
|
| Aquí solo queda la **landing pública** del subdominio: lo primero que
| ve un visitante anónimo (`clinica-x.localhost`) antes de hacer login.
|
| El parámetro `{tenant_subdomain}` se inyecta automáticamente desde el
| dominio (ver `bootstrap/app.php`). Es solo un comodín para que el
| routing matchee — la resolución real la hace `ResolveTenant`.
|
| Después de pasar por `tenant.required`, la conexión BD ya tiene el
| `search_path` apuntando al schema correcto.
|
*/

Route::middleware(['tenant.required'])->group(function (): void {
    Route::middleware(['signed', 'throttle:60,1'])
        ->prefix('documentos-publicos')
        ->name('tenant.public.clinical-history.')
        ->group(function (): void {
            Route::get('pacientes/{paciente}/historial', [PacienteController::class, 'publicHistorialView'])
                ->name('historial.view');
            Route::get('consultas/{consulta}.pdf', [ConsultaHistoriaController::class, 'publicPdf'])
                ->name('consulta');
            Route::get('pacientes/{paciente}/historial-clinico.pdf', [PacienteController::class, 'publicHistorialClinicoPdf'])
                ->name('historial');
            Route::get('vacunas/{vacuna_aplicada}/aplicacion.pdf', [VacunacionController::class, 'publicAplicacionPdf'])
                ->name('aplicacion');
            Route::get('laboratorio/lineas/{linea}/archivo', [LaboratorioController::class, 'publicDownloadResultadoArchivo'])
                ->name('laboratorio-archivo');
        });

    Route::get('/', [TenantDashboardController::class, 'welcome'])
        ->name('tenant.home');
});
