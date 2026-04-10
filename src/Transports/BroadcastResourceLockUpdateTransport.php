<?php

namespace Androsamp\FilamentResourceLock\Transports;

use Androsamp\FilamentResourceLock\Contracts\ResourceLockUpdateTransport;
use Androsamp\FilamentResourceLock\Events\ResourceLockUpdated;
use Androsamp\FilamentResourceLock\Support\ResourceLockBroadcastChannel;
use Illuminate\Database\Eloquent\Model;

class BroadcastResourceLockUpdateTransport implements ResourceLockUpdateTransport
{
    public function send(Model $record, string $event, array $payload = []): void
    {
        broadcast(new ResourceLockUpdated(
            channel: ResourceLockBroadcastChannel::forRecord($record),
            event: $event,
            payload: $payload,
        ));
    }
}
