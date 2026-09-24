<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PosDisplaySyncEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $token;
    public array $payload;

    /**
     * Create a new event instance.
     *
     * @param string $token Cashier station/client token
     * @param array $payload State payload (cart, totals, payment, customer, status, etc.)
     */
    public function __construct(string $token, array $payload)
    {
        $this->token = $token;
        $this->payload = $payload;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel
     */
    public function broadcastOn(): Channel
    {
        return new Channel('pos-display.' . $this->token);
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'PosDisplaySync';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith(): array
    {
        return array_merge([
            'token' => $this->token,
            'timestamp' => now()->toIso8601String(),
        ], $this->payload);
    }
}
