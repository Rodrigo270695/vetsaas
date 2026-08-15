<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Distrito;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida que el distrito exista en el catálogo geo global (`public.distritos`).
 *
 * No usar `exists:public.distritos,id`: Laravel interpreta `public` como
 * *nombre de conexión* (connection.table), no como schema de PostgreSQL,
 * y dispara `Database connection [public] not configured`.
 */
final class ExistsDistritoId implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_numeric($value)) {
            $fail(__('validation.exists', ['attribute' => $attribute]));

            return;
        }

        if (! Distrito::query()->whereKey((int) $value)->exists()) {
            $fail(__('validation.exists', ['attribute' => $attribute]));
        }
    }
}
