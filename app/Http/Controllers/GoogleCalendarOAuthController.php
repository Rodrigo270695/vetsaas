<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Google\GoogleCalendarMeetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Conecta la cuenta Google organizadora de Meet para el SalesBot.
 */
final class GoogleCalendarOAuthController extends Controller
{
    public function __construct(
        private readonly GoogleCalendarMeetService $google,
    ) {}

    public function connect(Request $request): RedirectResponse
    {
        if (! $this->google->hasClientCredentials()) {
            return redirect()
                ->route('plataforma.salesbot-conversations.index')
                ->with('error', 'Configura GOOGLE_CALENDAR_CLIENT_ID y CLIENT_SECRET en el .env.');
        }

        $state = Str::random(40);
        $request->session()->put('google_calendar_oauth_state', $state);

        try {
            return redirect()->away($this->google->authorizationUrl($state));
        } catch (Throwable $e) {
            Log::error('Google Calendar connect failed', ['error' => $e->getMessage()]);

            return redirect()
                ->route('plataforma.salesbot-conversations.index')
                ->with('error', 'No se pudo iniciar la conexión con Google.');
        }
    }

    public function callback(Request $request): RedirectResponse
    {
        $expected = (string) $request->session()->pull('google_calendar_oauth_state', '');
        $state = (string) $request->query('state', '');

        if ($expected === '' || ! hash_equals($expected, $state)) {
            return redirect()
                ->route('plataforma.salesbot-conversations.index')
                ->with('error', 'Sesión OAuth inválida. Vuelve a conectar Google Calendar.');
        }

        if ($request->filled('error')) {
            return redirect()
                ->route('plataforma.salesbot-conversations.index')
                ->with('error', 'Google denegó el acceso: '.$request->string('error'));
        }

        $code = trim((string) $request->query('code', ''));
        if ($code === '') {
            return redirect()
                ->route('plataforma.salesbot-conversations.index')
                ->with('error', 'Google no devolvió código de autorización.');
        }

        try {
            $this->google->exchangeAuthorizationCode($code);
        } catch (Throwable $e) {
            Log::error('Google Calendar callback failed', ['error' => $e->getMessage()]);

            return redirect()
                ->route('plataforma.salesbot-conversations.index')
                ->with('error', $e->getMessage());
        }

        $email = $this->google->status()['email'] ?? null;
        $msg = $email
            ? "Google Calendar conectado ({$email}). El bot ya puede crear Meet."
            : 'Google Calendar conectado. El bot ya puede crear Meet.';

        return redirect()
            ->route('plataforma.salesbot-conversations.index')
            ->with('success', $msg);
    }

    public function disconnect(): RedirectResponse
    {
        $this->google->disconnect();

        return redirect()
            ->route('plataforma.salesbot-conversations.index')
            ->with('success', 'Cuenta de Google Calendar desconectada.');
    }
}
