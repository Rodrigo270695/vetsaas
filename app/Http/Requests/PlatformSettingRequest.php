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
            'in_app_assistant_announcement_title' => ['nullable', 'string', 'max:160'],
            'in_app_assistant_announcement_body' => ['nullable', 'string', 'max:2000'],
            'in_app_assistant_announcement_features' => ['nullable', 'array', 'max:4'],
            'in_app_assistant_announcement_features.*' => ['nullable', 'string', 'max:200'],
            'republish_announcement' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'in_app_assistant_daily_limit' => 'límite diario del asistente',
            'in_app_assistant_announcement_active' => 'mostrar novedad del asistente',
            'in_app_assistant_announcement_title' => 'título de la novedad',
            'in_app_assistant_announcement_body' => 'texto de la novedad',
            'in_app_assistant_announcement_features' => 'puntos de la novedad',
        ];
    }

    protected function prepareForValidation(): void
    {
        $features = $this->input('in_app_assistant_announcement_features');
        if (is_array($features)) {
            $features = array_values(array_map(
                static fn ($item) => is_string($item) ? trim($item) : '',
                array_slice($features, 0, 4),
            ));
        } else {
            $features = ['', '', '', ''];
        }

        while (count($features) < 4) {
            $features[] = '';
        }

        $this->merge([
            'in_app_assistant_announcement_active' => $this->boolean('in_app_assistant_announcement_active'),
            'republish_announcement' => $this->boolean('republish_announcement'),
            'in_app_assistant_announcement_title' => trim((string) $this->input('in_app_assistant_announcement_title', '')),
            'in_app_assistant_announcement_body' => trim((string) $this->input('in_app_assistant_announcement_body', '')),
            'in_app_assistant_announcement_features' => $features,
        ]);
    }
}
