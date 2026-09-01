<?php

declare(strict_types=1);

use App\Models\TenantProductReview;
use App\Models\User;
use App\Services\Reviews\TenantProductReviewService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesTestTenant;
use Tests\Support\RefreshDatabaseWithPgsqlSafety;

uses(RefreshDatabaseWithPgsqlSafety::class, CreatesTestTenant::class);

beforeEach(function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Las reseñas de tenant requieren PostgreSQL.');
    }

    $this->configureTenancyForTests();
    $this->seedPermissionsAndRoles();
    $this->createTestTenantWithSchema();
});

afterEach(function (): void {
    $this->tearDownTestTenant();
});

it('expone el prompt de reseña a cada usuario de la clínica hasta que envía', function (): void {
    $this->actingAs($this->testTenantAdmin);

    $this->get('http://'.$this->testTenantHost.'/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('product_review_prompt.clinic_name', 'Test Clinic')
            ->where('product_review_prompt.role_label', 'Administración')
            ->where('product_review_prompt.role_line', 'Administración de Test Clinic')
        );
});

it('permite cerrar el modal y no lo vuelve a mostrar el mismo día', function (): void {
    $this->actingAs($this->testTenantAdmin)
        ->from('http://'.$this->testTenantHost.'/dashboard')
        ->post('http://'.$this->testTenantHost.'/tenant/product-review/dismiss')
        ->assertRedirect();

    $row = TenantProductReview::query()->where('user_id', $this->testTenantAdmin->id)->first();
    expect($row)->not->toBeNull()
        ->and($row->submitted_at)->toBeNull();

    $this->get('http://'.$this->testTenantHost.'/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('product_review_prompt', null));
});

it('vuelve a mostrar el modal al día siguiente si no enviaron la reseña', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-08-31 10:00:00', 'America/Lima'));

    $this->actingAs($this->testTenantAdmin)
        ->post('http://'.$this->testTenantHost.'/tenant/product-review/dismiss')
        ->assertRedirect();

    Carbon::setTestNow(Carbon::parse('2026-09-01 09:00:00', 'America/Lima'));

    $this->get('http://'.$this->testTenantHost.'/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('product_review_prompt.role_line', 'Administración de Test Clinic')
        );

    Carbon::setTestNow();
});

it('publica la reseña con nombre, cargo y clínica para Orvae', function (): void {
    $comment = 'La agenda unificada y el historial nos permitieron atender con más claridad, sin perder tiempo entre módulos.';

    $this->actingAs($this->testTenantAdmin)
        ->from('http://'.$this->testTenantHost.'/dashboard')
        ->post('http://'.$this->testTenantHost.'/tenant/product-review', [
            'rating' => 5,
            'comment' => $comment,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->get('http://'.$this->testTenantHost.'/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('product_review_prompt', null));

    $public = app(TenantProductReviewService::class)->publicReviews();
    expect($public)->toHaveCount(1)
        ->and($public[0]['role_line'])->toBe('Administración de Test Clinic')
        ->and($public[0]['author_name'])->toBe($this->testTenantAdmin->name)
        ->and($public[0]['rating'])->toBe(5)
        ->and($public[0]['comment'])->toBe($comment);
});

it('atribuye recepcionista de la clínica con nombre y apellido', function (): void {
    $previousTeam = getPermissionsTeamId();
    setPermissionsTeamId((string) $this->testTenant->id);

    try {
        $recepcion = User::factory()->create([
            'name' => 'Lucía Mendoza Rojas',
            'email' => 'lucia-'.$this->testTenantSlug.'@test.local',
            'tenant_id' => $this->testTenant->id,
            'password' => Hash::make('password'),
            'is_active' => true,
            'must_change_password' => false,
            'email_verified_at' => now(),
        ]);
        $recepcion->assignRole('recepcionista');
    } finally {
        setPermissionsTeamId($previousTeam);
    }

    $comment = 'En recepción confirmamos citas por WhatsApp y el mostrador fluye sin papeles ni dobles agendamientos.';

    $this->actingAs($recepcion)
        ->post('http://'.$this->testTenantHost.'/tenant/product-review', [
            'rating' => 5,
            'comment' => $comment,
        ])
        ->assertRedirect();

    $public = app(TenantProductReviewService::class)->publicReviews();
    expect($public[0]['author_name'])->toBe('Lucía Mendoza Rojas')
        ->and($public[0]['role_label'])->toBe('Recepcionista')
        ->and($public[0]['role_line'])->toBe('Recepcionista de Test Clinic');
});
