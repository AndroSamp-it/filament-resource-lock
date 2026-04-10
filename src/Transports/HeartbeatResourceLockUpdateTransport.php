<?php

namespace Androsamp\FilamentResourceLock\Transports;

use Androsamp\FilamentResourceLock\Contracts\ResourceLockUpdateTransport;
use Illuminate\Database\Eloquent\Model;

class HeartbeatResourceLockUpdateTransport implements ResourceLockUpdateTransport
{
    public function send(Model $record, string $event, array $payload = []): void
    {
        // In heartbeat mode the client polls on its own schedule; no push is needed.
    }
}
