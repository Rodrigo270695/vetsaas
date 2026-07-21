<?php

use App\Models\ClinicSetting;

it('activa por defecto las notificaciones WhatsApp al crear o reprogramar citas', function (): void {
    $setting = new ClinicSetting;

    expect($setting->notificarCitaWhatsAppActivo())->toBeTrue();
});

it('respeta cuando la clínica desactiva las notificaciones WhatsApp de citas', function (): void {
    $setting = new ClinicSetting;
    $setting->notificar_cita_whatsapp_activo = false;

    expect($setting->notificarCitaWhatsAppActivo())->toBeFalse();
});
