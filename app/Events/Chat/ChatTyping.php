<?php

declare(strict_types=1);

namespace App\Events\Chat;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Indicador de escritura en tiempo real (Reverb/Pusher).
 */
final class ChatTyping implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $conversationId,
        public readonly string $userId,
        public readonly string $userName,
        public readonly string $at,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('tenant.'.$this->tenantId.'.chat.'.$this->conversationId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'chat.typing';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'user_id' => $this->userId,
            'user_name' => $this->userName,
            'at' => $this->at,
        ];
    }
}
