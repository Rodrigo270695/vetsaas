<?php

use App\Models\ClinicSetting;

it('activa por defecto todos los avisos WhatsApp de hotel', function (): void {
    $setting = new ClinicSetting;

    expect($setting->notificarHotelWhatsAppActivo('programada'))->toBeTrue()
        ->and($setting->notificarHotelWhatsAppActivo('reprogramada'))->toBeTrue()
        ->and($setting->notificarHotelWhatsAppActivo('confirmada'))->toBeTrue()
        ->and($setting->notificarHotelWhatsAppActivo('en_estancia'))->toBeTrue()
        ->and($setting->notificarHotelWhatsAppActivo('completada'))->toBeTrue()
        ->and($setting->notificarHotelWhatsAppActivo('cancelada'))->toBeTrue()
        ->and($setting->notificarHotelWhatsAppActivo('no_presento'))->toBeTrue()
        ->and($setting->notificarHotelWhatsAppActivo('bitacora'))->toBeTrue();
});

it('respeta cada aviso de hotel desactivado por el tenant', function (): void {
    $setting = new ClinicSetting;
    $setting->forceFill([
        'notificar_hotel_creado_whatsapp_activo' => false,
        'notificar_hotel_confirmado_whatsapp_activo' => false,
        'notificar_hotel_en_estancia_whatsapp_activo' => false,
        'notificar_hotel_completado_whatsapp_activo' => false,
        'notificar_hotel_cancelado_whatsapp_activo' => false,
        'notificar_hotel_no_presento_whatsapp_activo' => false,
        'notificar_hotel_bitacora_whatsapp_activo' => false,
    ]);

    expect($setting->notificarHotelWhatsAppActivo('programada'))->toBeFalse()
        ->and($setting->notificarHotelWhatsAppActivo('confirmada'))->toBeFalse()
        ->and($setting->notificarHotelWhatsAppActivo('en_estancia'))->toBeFalse()
        ->and($setting->notificarHotelWhatsAppActivo('completada'))->toBeFalse()
        ->and($setting->notificarHotelWhatsAppActivo('cancelada'))->toBeFalse()
        ->and($setting->notificarHotelWhatsAppActivo('no_presento'))->toBeFalse()
        ->and($setting->notificarHotelWhatsAppActivo('bitacora'))->toBeFalse();
});
