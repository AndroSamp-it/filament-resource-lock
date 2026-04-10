<?php

namespace Androsamp\FilamentResourceLock\Contracts;

use Illuminate\Database\Eloquent\Model;

interface ResourceLockUpdateTransport
{
    public function send(Model $record, string $event, array $payload = []): void;
}
