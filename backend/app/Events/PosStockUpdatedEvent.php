<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PosStockUpdatedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param array<int|string> $itemIds
     * @param string|null $token
     * @param string|null $tenantId
     */
    public function __construct(
        public array $itemIds = [],
        public ?string $token = null,
        public ?string $tenantId = null
    ) {
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [new Channel('pos-stock')];

        if ($this->token) {
            $channels[] = new Channel('pos-display.' . $this->token);
        }

        if ($this->tenantId) {
            $channels[] = new Channel('pos-stock.' . $this->tenantId);
        }

        return $channels;
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'PosStockUpdated';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'item_ids' => $this->itemIds,
            'token' => $this->token,
            'tenant_id' => $this->tenantId,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
