<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\RefreshDatabaseWithPgsqlSafety;

uses(RefreshDatabaseWithPgsqlSafety::class);

beforeEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('PlatformSuperadmin requiere PostgreSQL (teams Spatie).');
    }
});

it('reconoce al superadmin de plataforma aunque el team actual sea un tenant', function (): void {
    config(['permission.teams' => true]);
    app(PermissionRegistrar::class)->teams = true;

    $previousTeam = getPermissionsTeamId();
    setPermissionsTeamId(null);

    try {
        $role = Role::query()->create([
            'name' => 'superadmin',
            'guard_name' => 'web',
            'tenant_id' => null,
        ]);

        $user = User::factory()->create([
            'tenant_id' => null,
            'email_verified_at' => now(),
        ]);

        $user->assignRole($role);
    } finally {
        setPermissionsTeamId($previousTeam);
    }

    $tenantTeamId = (string) Str::uuid();
    setPermissionsTeamId($tenantTeamId);

    try {
        $fresh = $user->fresh();
        expect($fresh->hasRole('superadmin'))->toBeFalse();
        expect($fresh->isPlatformSuperadmin())->toBeTrue();
        expect(Gate::forUser($fresh)->allows('dashboard.view'))->toBeTrue();
    } finally {
        setPermissionsTeamId(null);
    }
});

it('no marca como superadmin de plataforma a un usuario de clínica', function (): void {
    $user = User::factory()->create([
        'tenant_id' => (string) Str::uuid(),
    ]);

    expect($user->isPlatformSuperadmin())->toBeFalse();
});
