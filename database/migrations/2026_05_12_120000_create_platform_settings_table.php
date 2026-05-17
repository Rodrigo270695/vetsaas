<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Configuración global de la plataforma (SaaS-level).
 *
 * Vive en `public.platform_settings`. Es un singleton (una sola fila en
 * toda la instalación) que guarda credenciales de proveedores externos
 * compartidos por TODAS las clínicas:
 *
 *   • Twilio Cloud (WhatsApp) → recordatorios de citas y notificaciones.
 *   • Brevo (correo transaccional) → invitaciones, reset password, alertas.
 *
 * Razón de ser global y no por tenant:
 *   • Modelo de negocio "todo incluido": las clínicas pequeñas no quieren
 *     abrir cuentas de Twilio/Brevo ni gestionar el aprobado de WhatsApp.
 *   • El SaaS factura el uso (mensajes/correos) dentro del plan.
 *   • Las credenciales son del operador de la plataforma, no del cliente.
 *
 * El cliente solo puede personalizar el "remitente comercial visible"
 * (nombre y correo de respuesta) en `cfg_clinic_settings`, que NO altera
 * la autenticación con los proveedores.
 *
 * Las credenciales sensibles se cifran con `Crypt::encryptString` antes
 * de persistirse (AES-256-CBC derivada de APP_KEY).
 *
 * Patrón singleton-por-instalación: `CREATE UNIQUE INDEX ... ON ((TRUE))`
 * garantiza a nivel de Postgres que no puede existir más de una fila.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // ── Twilio (WhatsApp Cloud / SMS) ──
            $table->text('twilio_sid_enc')->nullable();
            $table->text('twilio_token_enc')->nullable();
            // Número aprobado por Meta para enviar plantillas (formato E.164,
            // ej: `+14155238886`). Único: todas las clínicas envían desde
            // este número (con su nombre comercial en el cuerpo del mensaje).
            $table->string('twilio_default_from', 30)->nullable();
            $table->boolean('twilio_configurado')->default(false);

            // ── Brevo (correo transaccional) ──
            $table->text('brevo_api_key_enc')->nullable();
            // Correo verificado en Brevo desde el cual sale todo (típicamente
            // `no-reply@vetsaas.com`). El cliente puede definir un Reply-To
            // distinto vía `cfg_clinic_settings.email_from`.
            $table->string('brevo_default_from_email', 150)->nullable();
            $table->string('brevo_default_from_name', 100)->nullable();
            $table->boolean('brevo_configurado')->default(false);

            $table->timestampsTz();
            $table->uuid('updated_by_id')->nullable();
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            // Auditoría: quién hizo el último cambio (debe ser superadmin).
            DB::statement('ALTER TABLE platform_settings ADD CONSTRAINT platform_settings_updated_by_fk FOREIGN KEY (updated_by_id) REFERENCES users (id) ON DELETE SET NULL');
            // Singleton-por-instalación: índice único sobre TRUE.
            DB::statement('CREATE UNIQUE INDEX uq_platform_settings_single_row ON platform_settings ((TRUE))');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
