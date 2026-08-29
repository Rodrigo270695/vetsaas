<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\DemoAccessLog;
use App\Support\Database\PublicSchema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Captura celular/correo (y nombre de clínica opcional) tras entrar a la demo.
 */
class DemoAccessLeadController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        abort_unless(is_public_demo_tenant(), 404);

        if (! PublicSchema::hasTable('demo_access_logs')) {
            return response()->json([
                'ok' => false,
                'message' => 'Falta migrar demo_access_logs.',
            ], 503);
        }

        $validated = $request->validate([
            'log_id' => ['nullable', 'uuid'],
            'skip' => ['sometimes', 'boolean'],
            'clinic_name' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:150'],
        ]);

        $skip = (bool) ($validated['skip'] ?? false);
        $clinicName = filled($validated['clinic_name'] ?? null)
            ? trim((string) $validated['clinic_name'])
            : null;
        $phone = filled($validated['phone'] ?? null)
            ? trim((string) $validated['phone'])
            : null;
        $email = filled($validated['email'] ?? null)
            ? strtolower(trim((string) $validated['email']))
            : null;

        if (! $skip && $phone === null && $email === null) {
            throw ValidationException::withMessages([
                'phone' => 'Indica un celular o un correo.',
                'email' => 'Indica un celular o un correo.',
            ]);
        }

        $log = $this->resolveLog($request, $validated['log_id'] ?? null);
        $now = Carbon::now();

        if ($skip) {
            $log->fill([
                'lead_skipped_at' => $now,
            ]);
            if ($log->created_at === null) {
                $log->created_at = $now;
            }
            $log->save();

            return response()->json([
                'ok' => true,
                'id' => $log->id,
                'skipped' => true,
            ]);
        }

        $log->fill([
            'clinic_name' => $clinicName,
            'phone' => $phone,
            'email' => $email,
            'lead_captured_at' => $now,
            'lead_skipped_at' => null,
            'ip' => $log->ip ?: $request->ip(),
            'user_agent' => $log->user_agent ?: substr((string) $request->userAgent(), 0, 2000),
            'user_id' => $log->user_id ?: $request->user()?->id,
        ]);
        if ($log->created_at === null) {
            $log->created_at = $now;
        }
        $log->save();

        return response()->json([
            'ok' => true,
            'id' => $log->id,
            'skipped' => false,
        ]);
    }

    private function resolveLog(Request $request, ?string $logId): DemoAccessLog
    {
        if ($logId !== null) {
            $byId = DemoAccessLog::query()->find($logId);
            if ($byId !== null) {
                return $byId;
            }
        }

        $recent = DemoAccessLog::query()
            ->where('ip', $request->ip())
            ->where('created_at', '>=', Carbon::now()->subHours(6))
            ->whereNull('lead_captured_at')
            ->orderByDesc('created_at')
            ->first();

        if ($recent !== null) {
            return $recent;
        }

        return new DemoAccessLog([
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 2000),
            'user_id' => $request->user()?->id,
            'created_at' => Carbon::now(),
        ]);
    }
}
