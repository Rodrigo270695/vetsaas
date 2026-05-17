<?php

namespace App\Console\Commands;

use App\Auth\TenantAwarePasswordTokenRepository;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\Auth\TenantAdminInvitationNotification;
use App\Support\Auth\AuthNotifier;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Crea el primer usuario administrador de una clínica.
 *
 * Arquitectura "single-login + datos aislados":
 *   El usuario vive en `public.users` con `tenant_id` apuntando a la
 *   clínica. Se le asigna el rol Spatie `admin_clinica`, que ya tiene
 *   sembrados los permisos operativos (ver `TenantRolesSeeder`).
 *
 * Modos de uso (Fase 2.6):
 *
 *   A) "Invitación por email" (recomendado):
 *      No pases `--password`. El comando genera una contraseña
 *      temporal aleatoria, marca al usuario con `must_change_password`
 *      y envía un correo con un magic-link de reset. El admin define
 *      su contraseña él mismo, soporte nunca la conoce.
 *
 *        php artisan vetsaas:tenant-create-admin <slug>
 *            --email=admin@clinica.test
 *            --name="María Quispe"
 *
 *   B) "Password explícito" (compatibilidad / debugging):
 *      Pasa `--password` y se asigna directo. Se queda con
 *      `must_change_password = true` por defecto, salvo que pases
 *      `--no-force-change` (no recomendado fuera de tests).
 *
 *        php artisan vetsaas:tenant-create-admin <slug>
 *            --email=admin@clinica.test
 *            --password=secreto123
 *            --name="María Quispe"
 *
 * El correo se encola en `mails`; ejecuta `php artisan queue:work
 * --queue=mails` para procesarlo en desarrollo.
 */
class TenantCreateAdminCommand extends Command
{
    protected $signature = 'vetsaas:tenant-create-admin
                            {slug : Slug del tenant (subdominio)}
                            {--email= : Email del nuevo admin}
                            {--password= : Contraseña explícita. Si se omite, se genera una aleatoria y se envía invitación}
                            {--name= : Nombre completo del admin (ej. "María Quispe")}
                            {--phone= : Teléfono de contacto (opcional)}
                            {--no-invite : No enviar correo de invitación (útil en tests)}
                            {--no-force-change : No marcar must_change_password (no recomendado fuera de tests/seeds)}
                            {--force : No preguntar si el email ya existe (sobreescribe contraseña)}';

    protected $description = 'Crea o actualiza el primer admin de una clínica. Por defecto envía invitación por correo.';

    public function handle(): int
    {
        $slug = (string) $this->argument('slug');

        $tenant = Tenant::query()->where('slug', $slug)->first();
        if ($tenant === null) {
            $this->error("Tenant con slug \"$slug\" no encontrado en public.tenants.");
            $this->line('Crea primero el tenant (panel SaaS o seed) y vuelve a intentar.');

            return self::FAILURE;
        }

        $email = $this->option('email') ?: $this->ask('Email del admin');
        $name = $this->option('name') ?: $this->ask('Nombre completo (ej. "María Quispe")');
        $phone = $this->option('phone');

        $explicitPassword = $this->option('password');
        $isInvitationMode = $explicitPassword === null;
        $password = $isInvitationMode
            ? Str::password(length: 20, symbols: false)
            : $explicitPassword;

        try {
            $payload = validator([
                'email' => $email,
                'password' => $password,
                'name' => $name,
                'phone' => $phone,
            ], [
                'email' => ['required', 'email', 'max:150'],
                'password' => ['required', 'string', 'min:8', 'max:200'],
                'name' => ['required', 'string', 'max:120'],
                'phone' => ['nullable', 'string', 'max:30'],
            ])->validate();
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $msg) {
                    $this->error("[$field] $msg");
                }
            }

            return self::FAILURE;
        }

        // Buscamos por (tenant_id, email): el mismo email puede existir
        // en otras clínicas o en el panel central sin colisionar.
        $existing = User::query()
            ->where('tenant_id', $tenant->id)
            ->where('email', $payload['email'])
            ->first();

        if ($existing !== null && ! $this->option('force')) {
            if (! $this->confirm("El email {$payload['email']} ya existe en esta clínica. ¿Sobreescribir contraseña y forzar rol admin_clinica?", false)) {
                $this->warn('Operación cancelada por el usuario.');

                return self::SUCCESS;
            }
        }

        $mustChangePassword = ! $this->option('no-force-change');

        $values = [
            'tenant_id' => $tenant->id,
            'name' => $payload['name'],
            'email' => $payload['email'],
            'password' => Hash::make($payload['password']),
            'phone' => $payload['phone'] ?? null,
            'is_active' => true,
            'must_change_password' => $mustChangePassword,
            'email_verified_at' => now(),
        ];

        $result = DB::transaction(function () use ($existing, $values): array {
            if ($existing !== null) {
                $existing->forceFill($values)->save();
                $existing->syncRoles(['admin_clinica']);

                return ['status' => 'updated', 'user' => $existing->fresh()];
            }

            $user = new User;
            $user->forceFill($values)->save();
            $user->syncRoles(['admin_clinica']);

            return ['status' => 'created', 'user' => $user->fresh()];
        });

        $accion = $result['status'] === 'created' ? 'creado' : 'actualizado';
        $this->info("Admin $accion correctamente en la clínica \"$slug\".");
        $this->line("  · Email: {$payload['email']}");
        $this->line("  · Nombre: {$payload['name']}");
        $this->line('  · Rol: admin_clinica');
        $this->line('  · URL de login: http://'.$slug.'.'.config('tenant.root_domain').':8000/login');
        $this->line('  · Cambio de password obligatorio: '.($mustChangePassword ? 'sí' : 'no'));

        $shouldSendInvite = $isInvitationMode && ! $this->option('no-invite');
        if ($shouldSendInvite) {
            $this->sendInvitation($result['user']);
        }

        return self::SUCCESS;
    }

    /**
     * Genera un token de password-reset y envía la notificación de
     * invitación al admin. Se ejecuta solo cuando el comando creó al
     * usuario sin password explícito (modo A) y no se pasó `--no-invite`.
     *
     * El token se almacena en `password_reset_tokens` con `tenant_id`
     * gracias a {@see TenantAwarePasswordTokenRepository}.
     */
    protected function sendInvitation(User $user): void
    {
        /** @var PasswordBroker $broker */
        $broker = Password::broker(config('fortify.passwords'));
        $token = $broker->createToken($user);

        AuthNotifier::send($user, new TenantAdminInvitationNotification($token));

        $queued = config('mail.queue_auth_notifications', false);
        $this->line($queued
            ? '  · Invitación encolada (cola "mails"; requiere queue:work).'
            : '  · Invitación enviada por correo.');
    }
}
