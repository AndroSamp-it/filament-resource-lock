<?php

namespace Androsamp\FilamentResourceLock\Services;

use Androsamp\FilamentResourceLock\Contracts\ResourceLockUpdateTransport;
use Androsamp\FilamentResourceLock\Transports\BroadcastResourceLockUpdateTransport;
use Androsamp\FilamentResourceLock\Transports\HeartbeatResourceLockUpdateTransport;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class ResourceLockUpdateDispatcher
{
    public function dispatch(Model $record, string $event, array $payload = []): void
    {
        $this->resolveTransport()->send($record, $event, $payload);
    }

    protected function resolveTransport(): ResourceLockUpdateTransport
    {
        $driver = (string) config('filament-resource-lock.update_driver', 'heartbeat');

        return match ($driver) {
            'heartbeat' => app(HeartbeatResourceLockUpdateTransport::class),
            'broadcast' => app(BroadcastResourceLockUpdateTransport::class),
            default => throw new InvalidArgumentException(sprintf(
                'Unknown filament-resource-lock update driver [%s].',
                $driver
            )),
        };
    }
}
