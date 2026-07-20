<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\Plan\PlanLimits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TenantPlanLimitsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('plataforma-tenants.update') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'overrides' => ['required', 'array'],
            'overrides.*.feature' => [
                'required',
                'string',
                Rule::in(PlanLimits::INT_LIMIT_FEATURES),
            ],
            'overrides.*.extra' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'overrides.*.override' => ['nullable', 'integer', 'min:-1', 'max:1000000'],
            'overrides.*.motivo' => ['nullable', 'string', 'max:255'],
            'overrides.*.expires_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return list<array{
     *     feature: string,
     *     extra: int,
     *     override: int|null,
     *     motivo: string|null,
     *     expires_at: string|null
     * }>
     */
    public function normalizedOverrides(): array
    {
        $out = [];
        $seen = [];

        foreach ($this->validated('overrides') as $row) {
            $feature = (string) $row['feature'];
            if (isset($seen[$feature])) {
                continue;
            }
            $seen[$feature] = true;

            $extra = max(0, (int) ($row['extra'] ?? 0));
            $override = array_key_exists('override', $row) && $row['override'] !== null && $row['override'] !== ''
                ? (int) $row['override']
                : null;
            $motivo = filled($row['motivo'] ?? null) ? trim((string) $row['motivo']) : null;
            $expires = filled($row['expires_at'] ?? null) ? (string) $row['expires_at'] : null;

            $out[] = [
                'feature' => $feature,
                'extra' => $extra,
                'override' => $override,
                'motivo' => $motivo,
                'expires_at' => $expires,
            ];
        }

        return $out;
    }
}
