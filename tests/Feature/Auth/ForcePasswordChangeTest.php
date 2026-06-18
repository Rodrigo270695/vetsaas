<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

uses(RefreshDatabase::class);

/**
 * Fase 2.6 — Flujo de cambio obligatorio de contraseña.
 *
 * Estos tests son agnósticos del driver (corren en SQLite también),
 * porque solo dependen del modelo User + middleware + controlador,
 * sin tocar schemas de tenant.
 */

beforeEach(function (): void {
    // El test pega contra la vista Inertia recién creada. Como Vite
    // no ha compilado el manifest en CI ni en local-sin-build,
    // desactivamos su lookup para no fallar por un asset estático.
    $this->withoutVite();

    $this->user = User::factory()->create([
        'must_change_password' => true,
        'password' => Hash::make('temp-password'),
    ]);
});

it('redirige cualquier ruta operativa al cambio de contraseña si el flag está activo', function (): void {
    $response = $this
        ->actingAs($this->user, 'web')
        ->get(route('dashboard'));

    $response->assertRedirect(route('password.change.form'));
});

it('permite ver y postear /cuenta/cambiar-password aunque el flag esté activo', function (): void {
    $this
        ->actingAs($this->user, 'web')
        ->get(route('password.change.form'))
        ->assertOk();
});

it('permite cerrar sesión aunque deba cambiar la contraseña', function (): void {
    $response = $this
        ->actingAs($this->user, 'web')
        ->post(route('logout'));

    $this->assertGuest();
    $response->assertRedirect();
});

it('actualiza la contraseña y baja el flag al hacer submit válido', function (): void {
    $response = $this
        ->actingAs($this->user, 'web')
        ->post(route('password.change.update'), [
            'password' => 'una-clave-segura-nueva',
            'password_confirmation' => 'una-clave-segura-nueva',
        ]);

    $response->assertRedirect();

    $fresh = $this->user->fresh();
    expect($fresh->must_change_password)->toBeFalse();
    expect(Hash::check('una-clave-segura-nueva', $fresh->password))->toBeTrue();
});

it('rechaza la nueva contraseña si coincide con la actual', function (): void {
    $response = $this
        ->actingAs($this->user, 'web')
        ->post(route('password.change.update'), [
            'password' => 'temp-password',
            'password_confirmation' => 'temp-password',
        ]);

    $response->assertSessionHasErrors('password');
    expect($this->user->fresh()->must_change_password)->toBeTrue();
});

it('rechaza confirmación distinta', function (): void {
    $response = $this
        ->actingAs($this->user, 'web')
        ->post(route('password.change.update'), [
            'password' => 'clave-a',
            'password_confirmation' => 'clave-b',
        ]);

    $response->assertSessionHasErrors('password');
});

it('devuelve error de validación en lugar de 500 cuando la contraseña es débil', function (): void {
    config(['app.debug' => false]);

    Password::defaults(fn () => Password::min(12)
        ->mixedCase()
        ->letters()
        ->numbers()
        ->symbols());

    $response = $this
        ->actingAs($this->user, 'web')
        ->withHeaders(['X-Inertia' => 'true'])
        ->post(route('password.change.update'), [
            'password' => '12345678',
            'password_confirmation' => '12345678',
        ]);

    expect($response->status())->not->toBe(500);
    $response->assertInvalid(['password']);
    expect($this->user->fresh()->must_change_password)->toBeTrue();
});

it('un usuario sin el flag entra al dashboard sin redirect', function (): void {
    $normal = User::factory()->create([
        'must_change_password' => false,
    ]);

    $response = $this
        ->actingAs($normal, 'web')
        ->get(route('dashboard'));

    expect($response->status())->not->toBe(302)
        ->or->expect($response->headers->get('Location'))->not->toBe(route('password.change.form'));
});
