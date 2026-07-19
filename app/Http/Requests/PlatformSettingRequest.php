<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PlatformSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'in_app_assistant_daily_limit' => ['required', 'integer', 'min:1', 'max:1000'],
            'in_app_assistant_announcement_active' => ['nullable', 'boolean'],
            'republish_announcement' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'in_app_assistant_daily_limit' => 'límite diario del asistente',
            'in_app_assistant_announcement_active' => 'mostrar novedad del asistente',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'in_app_assistant_announcement_active' => $this->boolean('in_app_assistant_announcement_active'),
            'republish_announcement' => $this->boolean('republish_announcement'),
        ]);
    }
}
