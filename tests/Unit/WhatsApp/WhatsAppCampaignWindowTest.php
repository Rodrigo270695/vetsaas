<?php

declare(strict_types=1);

use App\Models\WhatsAppCampana;
use App\Support\WhatsApp\WhatsAppCampaignClock;
use Illuminate\Support\Carbon;

it('interpreta la ventana de envío en hora Perú aunque el now esté en UTC', function (): void {
    $campana = new WhatsAppCampana([
        'hora_inicio' => '09:00:00',
        'hora_fin' => '23:05:00',
        'estado' => WhatsAppCampana::ESTADO_ENVIANDO,
        'intervalo_minutos' => 1,
    ]);

    $limaNight = Carbon::parse('2026-09-16 04:01:00', 'UTC');

    expect($campana->inSendWindow($limaNight))->toBeTrue()
        ->and(WhatsAppCampaignClock::now($limaNight)->format('H:i'))->toBe('23:01');

    $utcMorning = Carbon::parse('2026-09-16 04:10:00', 'UTC');

    expect($campana->inSendWindow($utcMorning))->toBeFalse();
});

it('lee H:i aunque postgres devuelva un datetime 1970', function (): void {
    expect(WhatsAppCampaignClock::hm('1970-01-01 09:00:00'))->toBe('09:00')
        ->and(WhatsAppCampaignClock::hm('23:05:00'))->toBe('23:05');
});
