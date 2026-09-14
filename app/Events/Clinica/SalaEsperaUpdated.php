<?php

declare(strict_types=1);

namespace App\Events\Clinica;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class SalaEsperaUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $item
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly string $action,
        public readonly string $tipo,
        public readonly array $item = [],
        public readonly ?string $actorId = null,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('tenant.'.$this->tenantId.'.sala-espera'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'sala-espera.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'tipo' => $this->tipo,
            'item' => $this->item,
            'actor_id' => $this->actorId,
        ];
    }
}
