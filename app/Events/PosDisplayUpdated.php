<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class PosDisplayUpdated implements ShouldBroadcastNow
{
    use Dispatchable;

    /** @param array<string, mixed> $payload */
    public function __construct(public int $shopId, public array $payload, public ?int $terminalId = null) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('shop.'.$this->shopId.($this->terminalId ? '.terminal.'.$this->terminalId : '').'.pos-display')];
    }

    public function broadcastAs(): string
    {
        return 'pos.display.updated';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
