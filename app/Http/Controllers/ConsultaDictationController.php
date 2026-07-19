<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Clinica\ConsultaDictationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

final class ConsultaDictationController extends Controller
{
    public function __invoke(Request $request, ConsultaDictationService $dictation): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless(
            $user->can('historias-clinicas.create') || $user->can('historias-clinicas.update'),
            403,
        );
        abort_unless((bool) config('consulta-dictation.enabled', true), 503);
        abort_unless($dictation->isConfigured(), 503, 'Dictado no configurado (falta OPENAI_API_KEY).');

        $maxKb = max(1024, (int) config('consulta-dictation.max_audio_kb', 12288));

        $data = $request->validate([
            'transcript' => ['nullable', 'string', 'min:3', 'max:20000'],
            'audio' => ['nullable', 'file', 'max:'.$maxKb],
        ]);

        if ($request->hasFile('audio')) {
            $ext = strtolower((string) $request->file('audio')->getClientOriginalExtension());
            $allowed = ['webm', 'wav', 'mp3', 'mp4', 'm4a', 'ogg', 'mpeg', 'x-m4a'];
            if ($ext !== '' && ! in_array($ext, $allowed, true)) {
                return response()->json([
                    'message' => 'Formato de audio no soportado.',
                ], 422);
            }
        }

        $hasAudio = $request->hasFile('audio');
        $transcript = trim((string) ($data['transcript'] ?? ''));

        if (! $hasAudio && $transcript === '') {
            return response()->json([
                'message' => 'Envía audio o una transcripción.',
            ], 422);
        }

        try {
            $result = $hasAudio
                ? $dictation->fromAudio($request->file('audio'))
                : $dictation->fromTranscript($transcript);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => $e->getMessage() !== '' ? $e->getMessage() : 'No pude procesar el dictado.',
            ], 502);
        }

        return response()->json([
            'ok' => true,
            'transcript' => $result['transcript'],
            'fields' => $result['fields'],
        ]);
    }
}
