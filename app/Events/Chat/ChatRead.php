<?php

declare(strict_types=1);

namespace App\Events\Chat;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Lectura de conversación en tiempo real (Reverb/Pusher).
 */
final class ChatRead implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $conversationId,
        public readonly string $userId,
        public readonly string $lastReadAt,
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
        return 'chat.read';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'user_id' => $this->userId,
            'last_read_at' => $this->lastReadAt,
        ];
    }
}
