<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\BotIaAnnouncement;
use Illuminate\Foundation\Http\FormRequest;

final class BotIaAnnouncementRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:200'],
            'badge' => ['required', 'string', 'in:'.implode(',', BotIaAnnouncement::BADGES)],
            'bullet_1' => ['required', 'string', 'max:500'],
            'bullet_2' => ['required', 'string', 'max:500'],
            'bullet_3' => ['required', 'string', 'max:500'],
            'guide_title' => ['nullable', 'string', 'max:200'],
            'guide_body' => ['nullable', 'string', 'max:2000'],
            'guide_tip_1' => ['nullable', 'string', 'max:500'],
            'guide_tip_2' => ['nullable', 'string', 'max:500'],
            'guide_tip_3' => ['nullable', 'string', 'max:500'],
            'is_active' => ['required', 'boolean'],
            'published_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
        ];
    }
}
