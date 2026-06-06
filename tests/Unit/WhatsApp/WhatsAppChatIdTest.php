<?php

declare(strict_types=1);

use App\Support\WhatsApp\WhatsAppChatId;

it('normaliza celular peruano de 9 dígitos', function (): void {
    expect(WhatsAppChatId::fromPhone('999 111 222'))->toBe('51999111222@c.us');
});

it('acepta número con prefijo 51', function (): void {
    expect(WhatsAppChatId::fromPhone('+51 999 111 222'))->toBe('51999111222@c.us');
});

it('rechaza teléfono vacío o inválido', function (): void {
    expect(WhatsAppChatId::fromPhone(null))->toBeNull();
    expect(WhatsAppChatId::fromPhone('123'))->toBeNull();
});
