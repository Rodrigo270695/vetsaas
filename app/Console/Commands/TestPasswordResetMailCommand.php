<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use App\Notifications\Auth\PasswordResetLinkNotification;
use App\Support\Auth\AuthNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Password;

/**
 * Prueba de envío de correo de restablecimiento de contraseña (soporte producción).
 */
class TestPasswordResetMailCommand extends Command
{
    protected $signature = 'vetsaas:test-password-reset-mail
                            {email : Email del usuario admin de la clínica}
                            {--slug= : Slug del tenant (recomendado en multi-tenant)}';

    protected $description = 'Envía un correo de prueba de reset de contraseña al usuario indicado';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        $slug = $this->option('slug') !== null
            ? strtolower(trim((string) $this->option('slug')))
            : null;

        $this->line('Config correo:');
        $this->line('  MAIL_MAILER='.config('mail.default'));
        $this->line('  MAIL_HOST='.config('mail.mailers.smtp.host'));
        $this->line('  MAIL_FROM='.config('mail.from.address'));
        $this->line('  MAIL_QUEUE_AUTH_NOTIFICATIONS='.(config('mail.queue_auth_notifications') ? 'true' : 'false'));

        if (config('mail.default') === 'log') {
            $this->warn('MAIL_MAILER=log → el correo NO llega al buzón; solo storage/logs/laravel.log');
        }

        $query = User::query()->where('email', $email);

        if ($slug !== null && $slug !== '') {
            $tenant = Tenant::query()->where('slug', $slug)->first();
            if ($tenant === null) {
                $this->error("Tenant no encontrado: {$slug}");

                return self::FAILURE;
            }
            $query->where('tenant_id', $tenant->id);
        }

        /** @var User|null $user */
        $user = $query->first();

        if ($user === null) {
            $this->error("Usuario no encontrado: {$email}".($slug ? " (slug {$slug})" : ''));

            return self::FAILURE;
        }

        $tenant = $user->tenant_id
            ? Tenant::query()->find($user->tenant_id)
            : null;

        $this->info("Usuario: {$user->name}");
        $this->line('  id: '.$user->id);
        if ($tenant) {
            $this->line('  clínica: '.$tenant->razon_social.' ['.$tenant->slug.']');
        }

        $broker = Password::broker(config('fortify.passwords'));
        $token = $broker->createToken($user);

        AuthNotifier::send($user, new PasswordResetLinkNotification($token));

        $url = (new PasswordResetLinkNotification($token))->buildResetUrl($user, $tenant);
        $this->line('  URL del enlace: '.$url);
        $this->info('Correo disparado. Revisa bandeja y carpeta spam.');

        if (config('mail.queue_auth_notifications')) {
            $this->warn('Cola activa: necesitas `php artisan queue:work --queue=mails` o MAIL_QUEUE_AUTH_NOTIFICATIONS=false');
        }

        return self::SUCCESS;
    }
}
