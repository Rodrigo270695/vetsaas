<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\UsesPublicSchema;
use App\Notifications\Auth\PasswordResetLinkNotification;
use App\Support\Auth\AuthNotifier;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable([
    'tenant_id',
    'name',
    'email',
    'phone',
    'documento_tipo',
    'documento_numero',
    'colegiatura',
    'cv_path',
    'dni_file_path',
    'firma_path',
    'password',
    'is_active',
    'must_change_password',
    'bootstrap_login_token',
    'bootstrap_login_expires_at',
    'last_login_at',
    'created_by_id',
])]
#[Hidden([
    'password',
    'two_factor_secret',
    'two_factor_recovery_codes',
    'remember_token',
    'bootstrap_login_token',
    'cv_path',
    'dni_file_path',
    'firma_path',
])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, HasUuids, Notifiable, SoftDeletes, TwoFactorAuthenticatable, UsesPublicSchema;

    private ?bool $isPlatformSuperadminMemo = null;

    /**
     * @var list<string>
     */
    protected $appends = [
        'cv_url',
        'dni_file_url',
        'firma_url',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'bootstrap_login_expires_at' => 'datetime',
            'last_login_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'last_path_at' => 'datetime',
        ];
    }

    public function getCvUrlAttribute(): ?string
    {
        return $this->publicDiskUrl($this->cv_path);
    }

    public function getDniFileUrlAttribute(): ?string
    {
        return $this->publicDiskUrl($this->dni_file_path);
    }

    public function getFirmaUrlAttribute(): ?string
    {
        return $this->publicDiskUrl($this->firma_path);
    }

    private function publicDiskUrl(?string $path): ?string
    {
        if (! filled($path)) {
            return null;
        }

        return asset('storage/'.ltrim((string) $path, '/'));
    }

    /**
     * Envía la notificación de reset de password.
     *
     * Override del default de Laravel para:
     *   - Usar nuestra notificación brandeada (Brevo SMTP, español, con
     *     marca de la clínica si aplica).
     *   - Por defecto se envía en el mismo request (sin depender de
     *     `queue:work`). Ver `config('mail.queue_auth_notifications')`.
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        AuthNotifier::send($this, new PasswordResetLinkNotification($token));
    }

    /**
     * Quién dio de alta a este usuario (autor del registro).
     * Es self-referencing y opcional: el primer superadmin no tiene padre.
     *
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * Clínica a la que pertenece este usuario.
     *
     * Si es `null`, el usuario es del panel central (superadmin / staff
     * de VetSaaS). Si tiene valor, es un empleado de esa clínica
     * (admin_clinica, veterinario, recepcionista, etc.).
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * ¿Es un usuario del panel central (sin tenant asignado)?
     * Típicamente: superadmin o staff de soporte interno de VetSaaS.
     */
    public function isCentral(): bool
    {
        return $this->tenant_id === null;
    }

    /**
     * ¿Es superadmin de plataforma (rol global con tenant_id null)?
     *
     * Con Spatie Teams activo, `hasRole('superadmin')` falla en hosts de
     * clínica porque el team actual es el UUID del tenant y el pivot del
     * superadmin queda en team null. Este helper consulta siempre con team null.
     */
    public function isPlatformSuperadmin(): bool
    {
        if ($this->isPlatformSuperadminMemo !== null) {
            return $this->isPlatformSuperadminMemo;
        }

        if (! $this->isCentral()) {
            return $this->isPlatformSuperadminMemo = false;
        }

        $previousTeam = getPermissionsTeamId();
        setPermissionsTeamId(null);

        try {
            // Evita reutilizar la relación `roles` cargada con otro team.
            $this->unsetRelation('roles');

            return $this->isPlatformSuperadminMemo = $this->hasRole('superadmin');
        } finally {
            setPermissionsTeamId($previousTeam);
            $this->unsetRelation('roles');
        }
    }

    /**
     * ¿Es un usuario operativo de una clínica?
     */
    public function isTenantUser(): bool
    {
        return $this->tenant_id !== null;
    }

    /**
     * Bot / cuenta de sistema (p. ej. Soporte VetSaaS @vetsaas.internal).
     * No debe listarse ni gestionarse desde Configuración → Usuarios del tenant.
     */
    public function isInternalSystemAccount(): bool
    {
        $email = strtolower(trim((string) $this->email));

        return $email !== '' && str_ends_with($email, '@vetsaas.internal');
    }

    /**
     * ¿Este usuario pertenece al tenant cuyo UUID se pasa?
     * `null` en el parámetro significa "panel central".
     */
    public function belongsToTenant(?string $tenantId): bool
    {
        return $this->tenant_id === $tenantId;
    }
}
