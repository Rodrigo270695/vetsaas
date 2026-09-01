<?php

declare(strict_types=1);

use App\Services\Platform\DatabaseSchemaInspector;
use Tests\TestCase;

uses(TestCase::class);

it('agrupa tablas del MER por dominio', function (): void {
    $inspector = new DatabaseSchemaInspector;

    expect($inspector->groupFor('users'))->toBe('identidad')
        ->and($inspector->groupFor('tenants'))->toBe('tenancy')
        ->and($inspector->groupFor('pacientes'))->toBe('clinica')
        ->and($inspector->groupFor('fel_comprobantes'))->toBe('fel')
        ->and($inspector->groupFor('grooming_servicios'))->toBe('grooming')
        ->and($inspector->groupFor('whatsapp_sessions'))->toBe('whatsapp')
        ->and($inspector->groupFor('sales_conversations'))->toBe('ventas_saas')
        ->and($inspector->groupFor('xyz_desconocida'))->toBe('otros');
});
