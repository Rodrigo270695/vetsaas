<?php

use App\Models\Propietario;
use App\Support\Fel\FelReceptorResolver;

it('resuelve tipo doc DNI desde propietario', function (): void {
    $prop = new Propietario([
        'tipo_documento' => 'DNI',
        'nombres' => 'Juan',
        'apellidos' => 'Pérez',
        'numero_documento' => '77344506',
    ]);

    $r = FelReceptorResolver::datosReceptor($prop);

    expect($r['tipo_doc'])->toBe(1)
        ->and($r['num_doc'])->toBe('77344506');
});

it('resuelve tipo doc RUC desde propietario', function (): void {
    $prop = new Propietario([
        'tipo_documento' => 'RUC',
        'razon_social' => 'Empresa SAC',
        'numero_documento' => '20611148217',
    ]);

    $r = FelReceptorResolver::datosReceptor($prop);

    expect($r['tipo_doc'])->toBe(6)
        ->and($r['num_doc'])->toBe('20611148217');
});

it('permite factura con DNI en caja sin forzar boleta por documento', function (): void {
    $prop = new Propietario([
        'tipo_documento' => 'DNI',
        'nombres' => 'María',
        'numero_documento' => '12345678',
    ]);

    $r = FelReceptorResolver::datosReceptor($prop);

    expect($r['tipo_doc'])->toBe(1)
        ->and($r['num_doc'])->toBe('12345678');
});
