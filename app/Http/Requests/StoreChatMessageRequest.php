<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $mimes = 'jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,txt,csv,zip';

        return [
            'body' => ['nullable', 'string', 'max:4000'],
            'reply_to_id' => ['nullable', 'uuid'],
            'mentioned_user_ids' => ['nullable', 'array', 'max:20'],
            'mentioned_user_ids.*' => ['uuid'],
            'attachment' => [
                'nullable',
                'file',
                'max:15360',
                "mimes:{$mimes}",
            ],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => [
                'file',
                'max:15360',
                "mimes:{$mimes}",
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $body = trim((string) $this->input('body', ''));
            $hasFile = $this->hasFile('attachment') || $this->hasFile('attachments');

            if ($body === '' && ! $hasFile) {
                $validator->errors()->add('body', __('Escribe un mensaje o adjunta un archivo.'));
            }
        });
    }
}
