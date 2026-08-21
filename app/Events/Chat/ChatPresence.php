<?php

declare(strict_types=1);

namespace App\Events\Chat;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Presencia online del chat (canal de conversación o tenant.presence).
 */
final class ChatPresence implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $userId,
        public readonly bool $online,
        public readonly ?string $lastSeenAt,
        public readonly ?string $conversationId = null,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        if ($this->conversationId !== null && $this->conversationId !== '') {
            return [
                new PrivateChannel('tenant.'.$this->tenantId.'.chat.'.$this->conversationId),
            ];
        }

        return [
            new PrivateChannel('tenant.'.$this->tenantId.'.chat.presence'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'chat.presence';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->userId,
            'online' => $this->online,
            'last_seen_at' => $this->lastSeenAt,
            'conversation_id' => $this->conversationId,
        ];
    }
}
