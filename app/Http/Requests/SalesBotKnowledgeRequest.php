<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SalesBotKnowledgeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('salesbot-knowledge.create')
            || $this->user()?->can('salesbot-knowledge.update')
            ?? false;
    }

    public function rules(): array
    {
        $id = $this->route('salesbotKnowledge')?->id;

        return [
            'product'    => ['required', 'string', 'max:50'],
            'section'    => ['required', 'string', 'in:plan,modulo,faq,objecion,novedad,general'],
            'slug'       => [
                'required',
                'string',
                'max:100',
                Rule::unique('salesbot_knowledge', 'slug')->ignore($id),
            ],
            'title'      => ['required', 'string', 'max:200'],
            'content'    => ['required', 'string'],
            'meta'       => ['nullable', 'array'],
            'sort_order' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:999'],
            'is_active'  => ['required', 'boolean'],
        ];
    }
}
