<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\ClinicSetting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $previousEmail = (string) $user->email;
        $emailChanged = isset($validated['email'])
            && strcasecmp((string) $validated['email'], $previousEmail) !== 0;

        $user->fill($validated);

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($emailChanged) {
            $this->syncClinicEmails($user, $previousEmail, (string) $validated['email']);

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->with(
                    'status',
                    'Tu correo se actualizó. Inicia sesión con el nuevo correo.',
                );
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * Mantiene alineados el correo de login, tenants.email_admin y
     * el correo institucional de Configuración → General.
     */
    private function syncClinicEmails(User $user, string $previousEmail, string $newEmail): void
    {
        if ($user->tenant_id === null) {
            return;
        }

        $tenant = Tenant::query()->find($user->tenant_id);
        if ($tenant === null) {
            return;
        }

        $isAdminClinica = $user->hasRole('admin_clinica');
        $wasTenantAdminEmail = strcasecmp((string) $tenant->email_admin, $previousEmail) === 0;

        if ($isAdminClinica || $wasTenantAdminEmail) {
            $tenant->forceFill(['email_admin' => $newEmail])->save();
        }

        try {
            $setting = ClinicSetting::current();
            $wasInstitutional = filled($setting->email_institucional)
                && strcasecmp((string) $setting->email_institucional, $previousEmail) === 0;

            if ($isAdminClinica || $wasInstitutional || blank($setting->email_institucional)) {
                $setting->forceFill([
                    'email_institucional' => $newEmail,
                    'updated_by_id' => $user->id,
                ])->save();
            }
        } catch (\Throwable) {
            // Sin schema de tenant resuelto no hay fila de clínica que sincronizar.
        }
    }
}
