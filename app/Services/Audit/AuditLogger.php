<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Audit\AuditActor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

final class AuditLogger
{
    /**
     * @param  array<string, mixed>|null  $cambios
     */
    public static function log(
        string $accion,
        string $modulo,
        ?string $tabla = null,
        ?string $registroId = null,
        ?string $registroLabel = null,
        ?array $cambios = null,
        ?Request $request = null,
    ): void {
        if (tenant_id() === null) {
            return;
        }

        try {
            /** @var User|null $user */
            $user = Auth::user();
            $req = $request ?? request();

            $actorNombre = $user?->name ?? AuditActor::nombre();
            $actorEmail = $user?->email ?? AuditActor::email();

            AuditLog::query()->create([
                'usuario_id' => $user?->getKey(),
                'usuario_nombre' => $actorNombre,
                'usuario_email' => $actorEmail,
                'accion' => $accion,
                'modulo' => $modulo,
                'tabla' => $tabla,
                'registro_id' => $registroId,
                'registro_label' => $registroLabel !== null && $registroLabel !== ''
                    ? mb_substr($registroLabel, 0, 255)
                    : null,
                'cambios' => $cambios,
                'ip_address' => $req?->ip(),
                'user_agent' => $req?->userAgent() !== null
                    ? mb_substr((string) $req->userAgent(), 0, 300)
                    : null,
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    public static function logModelEvent(Model $model, string $accion): void
    {
        $config = config('audit.observed_models.'.get_class($model));

        if (! is_array($config)) {
            return;
        }

        $labelMethod = $config['label_method'] ?? null;
        if (is_string($labelMethod) && method_exists($model, $labelMethod)) {
            $label = $model->{$labelMethod}();
        } else {
            $labelKey = (string) ($config['label'] ?? 'id');
            $label = data_get($model, $labelKey);
        }

        if ($label === null || $label === '') {
            $label = (string) $model->getKey();
        }

        $cambios = null;

        if ($accion === AuditLog::ACCION_UPDATED) {
            $cambios = self::diffChanges($model);

            if ($cambios === []) {
                return;
            }
        }

        self::log(
            accion: $accion,
            modulo: (string) $config['modulo'],
            tabla: $model->getTable(),
            registroId: $model->getKey() !== null ? (string) $model->getKey() : null,
            registroLabel: is_scalar($label) ? (string) $label : (string) $model->getKey(),
            cambios: $cambios,
        );
    }

    public static function logExport(string $modulo, ?string $descripcion = null): void
    {
        self::log(
            accion: AuditLog::ACCION_EXPORTED,
            modulo: $modulo,
            registroLabel: $descripcion,
        );
    }

    public static function logDownload(
        string $modulo,
        ?string $registroId = null,
        ?string $registroLabel = null,
        ?string $archivo = null,
    ): void {
        self::log(
            accion: AuditLog::ACCION_DOWNLOADED,
            modulo: $modulo,
            registroId: $registroId,
            registroLabel: $registroLabel ?? $archivo,
        );
    }

    /**
     * @return array<string, array{before: mixed, after: mixed}>
     */
    private static function diffChanges(Model $model): array
    {
        $hidden = config('audit.hidden_attributes', []);
        $dirty = $model->getDirty();
        $diff = [];

        foreach ($dirty as $attribute => $after) {
            if (in_array($attribute, $hidden, true)) {
                continue;
            }

            $diff[$attribute] = [
                'before' => $model->getOriginal($attribute),
                'after' => $after,
            ];
        }

        return $diff;
    }
}
