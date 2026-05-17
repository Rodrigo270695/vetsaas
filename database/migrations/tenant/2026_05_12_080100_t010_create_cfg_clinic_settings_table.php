<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            Schema::create('cfg_clinic_settings', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('ruc', 11)->nullable();
                $table->string('razon_social', 200)->nullable();
                $table->string('nombre_comercial', 150)->nullable();
                $table->string('direccion_fiscal', 255)->nullable();
                $table->unsignedBigInteger('distrito_id')->nullable();
                // Logo de la clínica: guardamos el path relativo dentro del
                // disco `public` (ej: `tenants/<slug>/logos/<uuid>.png`). La
                // URL pública se calcula en el accesor `logo_url` del modelo.
                $table->string('logo_path', 500)->nullable();
                $table->string('email_institucional', 150)->nullable();
                $table->string('telefono_principal', 20)->nullable();
                $table->string('web_url', 200)->nullable();
                $table->unsignedSmallInteger('duracion_cita_default_min')->default(30);
                $table->unsignedSmallInteger('intervalo_agenda_min')->default(15);
                $table->json('horario_atencion')->default('{}');
                $table->unsignedSmallInteger('dias_anticipacion_cita')->default(30);
                $table->boolean('recordatorio_48h_activo')->default(true);
                $table->boolean('recordatorio_2h_activo')->default(true);
                $table->boolean('recordatorio_vacuna_activo')->default(true);
                $table->unsignedSmallInteger('recordatorio_vacuna_dias_antes')->default(7);
                $table->boolean('recordatorio_cumple_activo')->default(false);
                // Nubefact: única integración 100% del cliente (cada clínica
                // tiene su propio RUC + token). Las boletas se emiten contra
                // el RUC del cliente, por eso jamás puede ser global.
                $table->text('nubefact_token_enc')->nullable();
                $table->string('nubefact_ruc', 11)->nullable();
                $table->boolean('nubefact_configurado')->default(false);
                // "Remitente comercial visible". NO autentica nada: solo es
                // el nombre/correo de respuesta y el número WhatsApp que el
                // cliente final ve en los mensajes. Las credenciales reales
                // de Twilio y Brevo viven en `public.platform_settings`
                // (singleton global gestionado por el superadmin).
                $table->string('whatsapp_display_number', 30)->nullable();
                $table->string('email_from', 150)->nullable();
                $table->string('email_from_nombre', 100)->nullable();
                $table->char('moneda', 3)->default('PEN');
                $table->decimal('igv_porcentaje', 5, 2)->default(18);
                $table->boolean('precio_incluye_igv')->default(true);
                $table->unsignedSmallInteger('horas_min_cancelacion')->default(24);
                $table->string('color_primario', 7)->nullable();
                $table->string('color_secundario', 7)->nullable();
                $table->timestampsTz();
                $table->uuid('updated_by_id')->nullable();
            });

            if (Schema::getConnection()->getDriverName() === 'pgsql') {
                // FK al catálogo geográfico global (distritos vive en
                // `public`, compartido por todos los tenants).
                DB::statement('ALTER TABLE cfg_clinic_settings ADD CONSTRAINT cfg_clinic_settings_distrito_fk FOREIGN KEY (distrito_id) REFERENCES public.distritos (id) ON DELETE SET NULL');
                // FK al modelo global de usuarios (single-login): los
                // empleados de la clínica viven en `public.users` con su
                // `tenant_id`. No hay tabla `users` dentro de cada schema.
                DB::statement('ALTER TABLE cfg_clinic_settings ADD CONSTRAINT cfg_clinic_settings_updated_by_fk FOREIGN KEY (updated_by_id) REFERENCES public.users (id) ON DELETE SET NULL');
                // Una sola fila por schema (clínica): índice único sobre TRUE.
                DB::statement('CREATE UNIQUE INDEX uq_cfg_clinic_settings_single_row ON cfg_clinic_settings ((TRUE))');
            }
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            Schema::dropIfExists('cfg_clinic_settings');
        });
    }
};
