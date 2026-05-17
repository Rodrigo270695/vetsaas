<?php

use App\Tenancy\Resolvers\SubdomainResolver;

/**
 * El resolver es PURO (no toca BD): solo parsea el host y aplica
 * las reglas de `tenant.central_domains` y `tenant.root_domain`.
 * Estos tests blindan los casos límite para evitar regresiones
 * que rompan la seguridad de aislamiento.
 */
beforeEach(function (): void {
    config([
        'tenant.central_domains' => ['localhost', '127.0.0.1', 'vetsaas.test'],
        'tenant.root_domain' => 'vetsaas.test',
    ]);
});

it('devuelve null para los dominios centrales', function (string $host): void {
    $resolver = app(SubdomainResolver::class);

    expect($resolver->resolveFromHost($host))->toBeNull();
})->with([
    'localhost',
    '127.0.0.1',
    'vetsaas.test',
]);

it('extrae el slug de subdominios válidos', function (string $host, string $expected): void {
    $resolver = app(SubdomainResolver::class);

    expect($resolver->resolveFromHost($host))->toBe($expected);
})->with([
    ['clinica-rivera.vetsaas.test', 'clinica-rivera'],
    ['CLINICA-RIVERA.VETSAAS.TEST', 'clinica-rivera'],
    ['rivera.vetsaas.test', 'rivera'],
    ['a1b2c3.vetsaas.test', 'a1b2c3'],
]);

it('rechaza subdominios mal formados', function (string $host): void {
    $resolver = app(SubdomainResolver::class);

    expect($resolver->resolveFromHost($host))->toBeNull();
})->with([
    'sub-subdomain (admin.clinica.vetsaas.test)' => 'admin.clinica.vetsaas.test',
    'slug que empieza con guion' => '-rivera.vetsaas.test',
    'slug que termina con guion' => 'rivera-.vetsaas.test',
    'slug con underscore (no es DNS válido)' => 'rivera_clinica.vetsaas.test',
    'dominio totalmente ajeno' => 'rivera.otrodominio.com',
    'host vacío' => '',
]);

it('rechaza intentos de inyección en el host', function (): void {
    $resolver = app(SubdomainResolver::class);

    expect($resolver->resolveFromHost('rivera"; DROP TABLE tenants;--.vetsaas.test'))
        ->toBeNull();
});
