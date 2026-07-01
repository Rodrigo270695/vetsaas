<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\ClinicBotKnowledge;
use App\Support\Subscriptions\BotIaAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ClinicBotKnowledgeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return BotIaAccess::userCanManage($this->user());
    }

    public function rules(): array
    {
        $id = $this->route('clinicBotKnowledge')?->id;

        return [
            'section' => ['required', 'string', Rule::in(ClinicBotKnowledge::SECTIONS)],
            'slug' => [
                'required',
                'string',
                'max:100',
                Rule::unique('clinic_bot_knowledge', 'slug')->ignore($id),
            ],
            'title' => ['required', 'string', 'max:200'],
            'content' => ['required', 'string'],
            'meta' => ['nullable', 'array'],
            'sort_order' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
