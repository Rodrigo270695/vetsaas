<?php

namespace App\Http\Controllers;

use App\Http\Requests\PlatformSettingRequest;
use App\Models\PlatformSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Configuración global de la plataforma (Plataforma → Configuración).
 */
class PlatformSettingController extends Controller
{
    public function show(): Response
    {
        $setting = PlatformSetting::current()->load('actualizadoPor:id,name,email');

        return Inertia::render('plataforma/configuracion/index', [
            'setting' => $this->presentSetting($setting),
        ]);
    }

    public function update(PlatformSettingRequest $request): RedirectResponse
    {
        $setting = PlatformSetting::current();
        $data = $request->validated();

        $setting->fill([
            'in_app_assistant_daily_limit' => (int) $data['in_app_assistant_daily_limit'],
            'updated_by_id' => Auth::id(),
        ]);
        $setting->save();

        return back()->with('success', 'Configuración global actualizada correctamente.');
    }

    /**
     * @return array<string, mixed>
     */
    private function presentSetting(PlatformSetting $setting): array
    {
        return [
            'id' => $setting->id,
            'in_app_assistant_daily_limit' => $setting->assistantDailyLimit(),
            'updated_at' => $setting->updated_at?->toIso8601String(),
            'actualizado_por' => $setting->actualizadoPor ? [
                'id' => $setting->actualizadoPor->id,
                'name' => $setting->actualizadoPor->name,
                'email' => $setting->actualizadoPor->email,
            ] : null,
        ];
    }
}
