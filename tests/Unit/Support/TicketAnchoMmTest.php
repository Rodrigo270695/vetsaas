<?php

declare(strict_types=1);

use App\Support\Caja\TicketAnchoMm;
use Illuminate\Http\Request;

it('normaliza anchos de ticket permitidos', function (): void {
    expect(TicketAnchoMm::normalize('56'))->toBe('56');
    expect(TicketAnchoMm::normalize('58'))->toBe('58');
    expect(TicketAnchoMm::normalize('80'))->toBe('80');
    expect(TicketAnchoMm::normalize('99', '80'))->toBe('80');
    expect(TicketAnchoMm::normalize(null, '80'))->toBe('80');
    expect(TicketAnchoMm::normalize(null, null))->toBe('58');
});

it('prioriza el query param ancho sobre la configuración', function (): void {
    $request = Request::create('/ticket', 'GET', ['ancho' => '56']);

    expect(TicketAnchoMm::fromRequest($request, '80'))->toBe('56');
});

it('usa el ancho útil del cabezal y no el del rollo', function (): void {
    expect(TicketAnchoMm::printableMm('80'))->toBe('72')
        ->and(TicketAnchoMm::printableMm('58'))->toBe('48')
        ->and(TicketAnchoMm::printableMm('56'))->toBe('48');
});

it('ajusta tipografía según ancho estrecho', function (): void {
    expect(TicketAnchoMm::typography('56')['fs'])->toBe(10)
        ->and(TicketAnchoMm::typography('58')['fs'])->toBe(10)
        ->and(TicketAnchoMm::typography('80')['fs'])->toBe(12)
        ->and(TicketAnchoMm::typography('80')['fs_sm'])->toBe(11);
});
