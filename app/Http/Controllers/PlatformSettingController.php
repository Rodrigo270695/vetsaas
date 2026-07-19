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

        $active = (bool) ($data['in_app_assistant_announcement_active'] ?? false);
        $republish = (bool) ($data['republish_announcement'] ?? false);
        $features = is_array($data['in_app_assistant_announcement_features'] ?? null)
            ? array_values(array_filter(
                array_map(
                    static fn ($item) => is_string($item) ? trim($item) : '',
                    $data['in_app_assistant_announcement_features'],
                ),
                static fn (string $item) => $item !== '',
            ))
            : [];

        $title = trim((string) ($data['in_app_assistant_announcement_title'] ?? ''));
        $body = trim((string) ($data['in_app_assistant_announcement_body'] ?? ''));

        $setting->fill([
            'in_app_assistant_daily_limit' => (int) $data['in_app_assistant_daily_limit'],
            'in_app_assistant_announcement_active' => $active,
            'in_app_assistant_announcement_title' => $title !== '' ? $title : null,
            'in_app_assistant_announcement_body' => $body !== '' ? $body : null,
            'in_app_assistant_announcement_features' => $features !== [] ? $features : null,
            'updated_by_id' => Auth::id(),
        ]);

        if ($republish) {
            $setting->in_app_assistant_announcement_active = true;
            $setting->in_app_assistant_announcement_version = ((int) $setting->in_app_assistant_announcement_version) + 1;
        } elseif ($active && (int) $setting->in_app_assistant_announcement_version < 1) {
            // Primera publicación: arranca en versión 1.
            $setting->in_app_assistant_announcement_version = 1;
        }

        $setting->save();

        return back()->with('success', 'Configuración global actualizada correctamente.');
    }

    /**
     * @return array<string, mixed>
     */
    private function presentSetting(PlatformSetting $setting): array
    {
        $features = $setting->assistantAnnouncementFeatures();
        while (count($features) < 4) {
            $features[] = '';
        }

        return [
            'id' => $setting->id,
            'in_app_assistant_daily_limit' => $setting->assistantDailyLimit(),
            'in_app_assistant_announcement_active' => (bool) $setting->in_app_assistant_announcement_active,
            'in_app_assistant_announcement_version' => (int) $setting->in_app_assistant_announcement_version,
            'in_app_assistant_announcement_title' => (string) ($setting->in_app_assistant_announcement_title ?? ''),
            'in_app_assistant_announcement_body' => (string) ($setting->in_app_assistant_announcement_body ?? ''),
            'in_app_assistant_announcement_features' => array_slice($features, 0, 4),
            'updated_at' => $setting->updated_at?->toIso8601String(),
            'actualizado_por' => $setting->actualizadoPor ? [
                'id' => $setting->actualizadoPor->id,
                'name' => $setting->actualizadoPor->name,
                'email' => $setting->actualizadoPor->email,
            ] : null,
        ];
    }
}
