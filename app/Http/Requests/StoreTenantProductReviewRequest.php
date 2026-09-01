<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\Reviews\TenantProductReviewService;
use Illuminate\Foundation\Http\FormRequest;

class StoreTenantProductReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['required', 'string', 'min:40', 'max:600'],
        ];
    }

    public function attributes(): array
    {
        return [
            'rating' => 'estrellas',
            'comment' => 'comentario',
        ];
    }

    public function messages(): array
    {
        return [
            'comment.min' => 'El comentario debe tener al menos 40 caracteres para publicarlo con seriedad.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $service = app(TenantProductReviewService::class);

        $this->merge([
            'comment' => $service->sanitizeComment((string) $this->input('comment', '')),
            'rating' => (int) $this->input('rating', 0),
        ]);
    }
}
