<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserAuthSessionLog;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Support\CreatesTestTenant;
use Tests\Support\RefreshDatabaseWithPgsqlSafety;

uses(RefreshDatabaseWithPgsqlSafety::class, CreatesTestTenant::class);

beforeEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Presencia de login requiere PostgreSQL.');
    }

    $this->configureTenancyForTests();
    $this->seedPermissionsAndRoles();
    $this->createTestTenantWithSchema();
});

afterEach(function (): void {
    $this->tearDownTestTenant();
});

it('actualiza last_login_at sin crear historial de sesión', function (): void {
    $this->actingAs($this->testTenantAdmin);
    Event::dispatch(new Login('web', $this->testTenantAdmin, false));

    $user = User::query()->findOrFail($this->testTenantAdmin->id);

    expect($user->last_login_at)->not->toBeNull();
    expect(UserAuthSessionLog::query()->where('user_id', $user->id)->count())->toBe(0);
});
