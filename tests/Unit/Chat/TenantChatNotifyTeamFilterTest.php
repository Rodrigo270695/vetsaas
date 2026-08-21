<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Chat\TenantChatService;
use Tests\TestCase;

uses(TestCase::class);

it('acepta solo emojis de reacción permitidos', function (): void {
    expect(TenantChatService::isAllowedReactionEmoji('👍'))->toBeTrue()
        ->and(TenantChatService::isAllowedReactionEmoji('✅'))->toBeTrue()
        ->and(TenantChatService::isAllowedReactionEmoji('❤️'))->toBeTrue()
        ->and(TenantChatService::isAllowedReactionEmoji('😂'))->toBeTrue()
        ->and(TenantChatService::isAllowedReactionEmoji('🎉'))->toBeTrue()
        ->and(TenantChatService::isAllowedReactionEmoji('🔥'))->toBeFalse()
        ->and(TenantChatService::isAllowedReactionEmoji(''))->toBeFalse()
        ->and(TenantChatService::isAllowedReactionEmoji('👍👍'))->toBeFalse();
});

it('califica miembros de caja por permiso ventas/caja o roles recepción/admin', function (): void {
    $withVentas = Mockery::mock(User::class);
    $withVentas->shouldReceive('can')->with('ventas.create')->andReturnTrue();
    $withVentas->shouldReceive('can')->with('caja-sesiones.view')->andReturnFalse();
    $withVentas->shouldReceive('hasRole')->andReturnFalse();

    $withCaja = Mockery::mock(User::class);
    $withCaja->shouldReceive('can')->with('ventas.create')->andReturnFalse();
    $withCaja->shouldReceive('can')->with('caja-sesiones.view')->andReturnTrue();
    $withCaja->shouldReceive('hasRole')->andReturnFalse();

    $recepcionista = Mockery::mock(User::class);
    $recepcionista->shouldReceive('can')->with('ventas.create')->andReturnFalse();
    $recepcionista->shouldReceive('can')->with('caja-sesiones.view')->andReturnFalse();
    $recepcionista->shouldReceive('hasRole')->with('recepcionista')->andReturnTrue();
    $recepcionista->shouldReceive('hasRole')->with('admin_clinica')->andReturnFalse();

    $admin = Mockery::mock(User::class);
    $admin->shouldReceive('can')->with('ventas.create')->andReturnFalse();
    $admin->shouldReceive('can')->with('caja-sesiones.view')->andReturnFalse();
    $admin->shouldReceive('hasRole')->with('recepcionista')->andReturnFalse();
    $admin->shouldReceive('hasRole')->with('admin_clinica')->andReturnTrue();

    $vet = Mockery::mock(User::class);
    $vet->shouldReceive('can')->with('ventas.create')->andReturnFalse();
    $vet->shouldReceive('can')->with('caja-sesiones.view')->andReturnFalse();
    $vet->shouldReceive('hasRole')->with('recepcionista')->andReturnFalse();
    $vet->shouldReceive('hasRole')->with('admin_clinica')->andReturnFalse();

    expect(TenantChatService::userQualifiesForCashTeam($withVentas))->toBeTrue()
        ->and(TenantChatService::userQualifiesForCashTeam($withCaja))->toBeTrue()
        ->and(TenantChatService::userQualifiesForCashTeam($recepcionista))->toBeTrue()
        ->and(TenantChatService::userQualifiesForCashTeam($admin))->toBeTrue()
        ->and(TenantChatService::userQualifiesForCashTeam($vet))->toBeFalse();
});

it('expone ALLOWED_REACTION_EMOJIS alineado con el whitelist', function (): void {
    expect(TenantChatService::ALLOWED_REACTION_EMOJIS)->toBe(['👍', '✅', '❤️', '😂', '🎉']);

    foreach (TenantChatService::ALLOWED_REACTION_EMOJIS as $emoji) {
        expect(TenantChatService::isAllowedReactionEmoji($emoji))->toBeTrue();
    }
});
