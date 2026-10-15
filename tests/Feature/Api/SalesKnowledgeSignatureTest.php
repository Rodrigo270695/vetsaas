<?php

declare(strict_types=1);

use App\Models\SalesBotKnowledge;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\OrvaeProvisionTestHelper;

beforeEach(function (): void {
    config(['orvae.provision.hmac_secret' => 'test-knowledge-secret']);
});

it('rechaza la consulta de conocimiento sin firma o con otro secreto', function (): void {
    $this->getJson('/api/internal/saas/salesbot-knowledge')->assertUnauthorized();

    $this->call(
        'GET',
        '/api/internal/saas/salesbot-knowledge',
        server: OrvaeProvisionTestHelper::signedGetServerVars('otro-secreto'),
    )->assertUnauthorized();
});

it('avisa a ORVAE con firma solo cuando cambia el conocimiento de VetSaaS', function (): void {
    Http::fake();
    config(['salesbot.orvae.refresh_url' => 'https://orvae.test/api/internal/saas/vetsaas/knowledge/refresh']);

    SalesBotKnowledge::flushCache('paginas-web');
    SalesBotKnowledge::flushCache('vetsaas');
    app()->terminate();

    Http::assertSentCount(1);
    Http::assertSent(function (Request $request): bool {
        $timestamp = $request->header('X-Orvae-Timestamp')[0];

        return $request->url() === 'https://orvae.test/api/internal/saas/vetsaas/knowledge/refresh'
            && $request->header('X-Orvae-Signature')[0] === 'sha256='.hash_hmac('sha256', $timestamp.'.'.$request->body(), 'test-knowledge-secret');
    });
});

it('no avisa a ORVAE si no hay URL configurada', function (): void {
    Http::fake();
    config(['salesbot.orvae.refresh_url' => null]);

    SalesBotKnowledge::flushCache('vetsaas');
    app()->terminate();

    Http::assertNothingSent();
});
