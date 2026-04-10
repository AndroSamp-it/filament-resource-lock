<?php

namespace Androsamp\FilamentResourceLock\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ResourceLockUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public string $channel,
        public string $event,
        public array $payload = [],
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel($this->channel)];
    }

    public function broadcastAs(): string
    {
        return (string) config('filament-resource-lock.transports.broadcast.event', 'resource-lock.updated');
    }

    public function broadcastWith(): array
    {
        return [
            'event' => $this->event,
            'payload' => $this->payload,
        ];
    }
}
