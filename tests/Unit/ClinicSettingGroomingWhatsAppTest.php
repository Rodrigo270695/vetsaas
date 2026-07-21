<?php

use App\Models\ClinicSetting;

it('activa por defecto todos los avisos WhatsApp de grooming', function (): void {
    $setting = new ClinicSetting;

    expect($setting->notificarGroomingWhatsAppActivo('programado'))->toBeTrue()
        ->and($setting->notificarGroomingWhatsAppActivo('reprogramado'))->toBeTrue()
        ->and($setting->notificarGroomingWhatsAppActivo('en_proceso'))->toBeTrue()
        ->and($setting->notificarGroomingWhatsAppActivo('completada'))->toBeTrue()
        ->and($setting->notificarGroomingWhatsAppActivo('cancelada'))->toBeTrue()
        ->and($setting->notificarGroomingWhatsAppActivo('no_asistio'))->toBeTrue();
});

it('respeta cada aviso de grooming desactivado por el tenant', function (): void {
    $setting = new ClinicSetting;
    $setting->forceFill([
        'notificar_grooming_creado_whatsapp_activo' => false,
        'notificar_grooming_en_proceso_whatsapp_activo' => false,
        'notificar_grooming_completado_whatsapp_activo' => false,
        'notificar_grooming_cancelado_whatsapp_activo' => false,
        'notificar_grooming_no_asistio_whatsapp_activo' => false,
    ]);

    expect($setting->notificarGroomingWhatsAppActivo('programado'))->toBeFalse()
        ->and($setting->notificarGroomingWhatsAppActivo('en_proceso'))->toBeFalse()
        ->and($setting->notificarGroomingWhatsAppActivo('completada'))->toBeFalse()
        ->and($setting->notificarGroomingWhatsAppActivo('cancelada'))->toBeFalse()
        ->and($setting->notificarGroomingWhatsAppActivo('no_asistio'))->toBeFalse();
});
