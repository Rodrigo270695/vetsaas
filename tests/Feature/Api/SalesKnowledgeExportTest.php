<?php

declare(strict_types=1);

use App\Models\SalesBotKnowledge;
use Database\Seeders\PlansAndFeaturesSeeder;
use Tests\Support\OrvaeProvisionTestHelper;
use Tests\Support\RefreshDatabaseWithPgsqlSafety;

uses(RefreshDatabaseWithPgsqlSafety::class);

beforeEach(function (): void {
    config(['orvae.provision.hmac_secret' => 'test-knowledge-secret']);
    $this->seed(PlansAndFeaturesSeeder::class);
});

it('entrega a ORVAE planes, secciones y demo de VetSaaS con firma válida', function (): void {
    SalesBotKnowledge::query()->create([
        'product' => 'vetsaas',
        'section' => 'novedad',
        'slug' => 'novedad-test-orvae',
        'title' => 'Novedad de prueba',
        'content' => 'Ahora con recordatorios.',
        'sort_order' => 1,
        'is_active' => true,
    ]);
    SalesBotKnowledge::query()->create([
        'product' => 'vetsaas',
        'section' => 'faq',
        'slug' => 'faq-inactiva-test',
        'title' => 'FAQ inactiva',
        'content' => 'No debe salir.',
        'is_active' => false,
    ]);

    $response = $this->call(
        'GET',
        '/api/internal/saas/salesbot-knowledge',
        server: OrvaeProvisionTestHelper::signedGetServerVars('test-knowledge-secret'),
    );

    $response->assertOk()
        ->assertJsonPath('version', 1)
        ->assertJsonPath('producto', 'vetsaas')
        ->assertJsonStructure([
            'nombre', 'resumen', 'publico', 'palabras_clave', 'registro_url', 'actualizado_at',
            'planes' => [['codigo', 'nombre', 'precio_mensual', 'precio_anual', 'moneda', 'trial_days', 'incluye']],
            'secciones' => [['tipo', 'titulo', 'contenido', 'fecha']],
            'demo' => ['url', 'usuario', 'password'],
        ]);

    $titulos = collect($response->json('secciones'))->pluck('titulo');
    expect($titulos)->toContain('Novedad de prueba')
        ->and($titulos)->not->toContain('FAQ inactiva')
        ->and(collect($response->json('secciones'))->pluck('tipo'))->not->toContain('plan')
        ->and($response->json('planes.0.incluye'))->not->toBeEmpty();
});
