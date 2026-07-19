<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InAppAssistantAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:2000'],
            'features' => ['nullable', 'array', 'max:4'],
            'features.*' => ['nullable', 'string', 'max:200'],
            'publish_now' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'título',
            'body' => 'texto principal',
            'features' => 'puntos destacados',
        ];
    }

    protected function prepareForValidation(): void
    {
        $features = $this->input('features');
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
            'title' => trim((string) $this->input('title', '')),
            'body' => trim((string) $this->input('body', '')),
            'features' => $features,
            'publish_now' => $this->boolean('publish_now'),
        ]);
    }
}
