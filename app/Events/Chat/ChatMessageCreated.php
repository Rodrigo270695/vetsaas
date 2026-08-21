<?php

declare(strict_types=1);

namespace App\Events\Chat;

use App\Models\ChatConversation;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento de chat para broadcasting (Reverb/Pusher) cuando BROADCAST_CONNECTION lo permita.
 */
final class ChatMessageCreated implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $message
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly string $conversationId,
        public readonly array $message,
        public readonly string $preview,
        public readonly string $senderName,
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
        return 'chat.message';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'message' => $this->message,
            'preview' => $this->preview,
            'sender_name' => $this->senderName,
        ];
    }
}
