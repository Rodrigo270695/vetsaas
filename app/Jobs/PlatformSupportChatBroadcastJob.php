<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Services\Chat\PlatformSupportChatService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

final class PlatformSupportChatBroadcastJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public readonly string $body,
        public readonly string $planFilter,
        public readonly ?string $platformActorId,
    ) {}

    public function handle(PlatformSupportChatService $service): void
    {
        $actor = $this->platformActorId !== null
            ? User::query()->find($this->platformActorId)
            : null;

        $result = $service->broadcast($this->body, $this->planFilter, $actor);

        Log::info('Platform support chat broadcast finished', [
            'plan' => $this->planFilter,
            'sent' => $result['sent'],
            'failed' => count($result['failed']),
        ]);
    }
}
