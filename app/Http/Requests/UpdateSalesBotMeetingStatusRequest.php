<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\SalesConversation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateSalesBotMeetingStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                Rule::in([
                    SalesConversation::MEET_STATUS_CONFIRMED,
                    SalesConversation::MEET_STATUS_COMPLETED,
                    SalesConversation::MEET_STATUS_NO_SHOW,
                    SalesConversation::MEET_STATUS_CANCELLED,
                ]),
            ],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
