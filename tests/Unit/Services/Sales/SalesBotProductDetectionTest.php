<?php

use App\Services\Sales\SalesBotService;

beforeEach(function (): void {
    $this->bot = app(SalesBotService::class);
});

it('detecta trigger de paginas web desde mensaje de anuncio', function (): void {
    $msg = '¡Hola! Quiero info sobre sus planes de página web.';

    expect($this->bot->detectSalesTrigger($msg))->toBe(SalesBotService::PRODUCT_PAGINAS_WEB);
});

it('detecta landing page y web administrable', function (): void {
    expect($this->bot->detectSalesTrigger('Me interesa una landing page'))->toBe(SalesBotService::PRODUCT_PAGINAS_WEB);
    expect($this->bot->detectSalesTrigger('Necesito web administrable'))->toBe(SalesBotService::PRODUCT_PAGINAS_WEB);
});

it('detecta software a medida como paginas web sin pausar handoff', function (): void {
    expect($this->bot->detectSalesTrigger('Quiero un software a medida'))->toBe(SalesBotService::PRODUCT_PAGINAS_WEB);
    expect($this->bot->isHumanHandoffMessage('Necesito software a medida', SalesBotService::PRODUCT_PAGINAS_WEB))->toBeFalse();
});

it('mantiene vetsaas para clinica veterinaria', function (): void {
    expect($this->bot->detectSalesTrigger('¿Cómo funciona VetSaaS para mi clínica?'))->toBe(SalesBotService::PRODUCT_VETSAAS);
});

it('resuelve producto desde trigger paginas-web', function (): void {
    expect($this->bot->resolveProductFromTrigger('paginas-web'))->toBe(SalesBotService::PRODUCT_PAGINAS_WEB);
    expect($this->bot->resolveProductFromTrigger('facebook:paginas-web'))->toBe(SalesBotService::PRODUCT_PAGINAS_WEB);
    expect($this->bot->resolveProductFromTrigger('vetsaas'))->toBe(SalesBotService::PRODUCT_VETSAAS);
});

it('detecta bienvenida facebook de paginas web', function (): void {
    $welcome = '¡Hola! Gracias por escribir. Tenemos planes de página web desde S/ 519.';

    expect($this->bot->detectFacebookWelcomeProduct($welcome))->toBe(SalesBotService::PRODUCT_PAGINAS_WEB);
});

it('detecta informacion sobre paginas web con acentos', function (): void {
    $msg = 'Hola, información sobre páginas web ?';

    expect($this->bot->detectSalesTrigger($msg))->toBe(SalesBotService::PRODUCT_PAGINAS_WEB);
});

it('detecta tienda virtual como paginas web plan 3', function (): void {
    expect($this->bot->detectSalesTrigger('Quiero una tienda virtual'))->toBe(SalesBotService::PRODUCT_PAGINAS_WEB);
});

it('pausa al detectar handoff a administrador', function (): void {
    $reply = 'Perfecto 🙌 Ya tengo claro tu proyecto. Te paso con Rodrigo, nuestro administrador, para cerrar.';

    expect($this->bot->shouldPauseForAdminHandoff($reply, SalesBotService::PRODUCT_PAGINAS_WEB))->toBeTrue();
    expect($this->bot->shouldPauseForAdminHandoff($reply, SalesBotService::PRODUCT_VETSAAS))->toBeFalse();
});
