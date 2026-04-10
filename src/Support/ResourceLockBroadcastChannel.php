<?php

namespace Androsamp\FilamentResourceLock\Support;

use Illuminate\Database\Eloquent\Model;

class ResourceLockBroadcastChannel
{
    public static function forRecord(Model $record): string
    {
        $prefix = (string) config('filament-resource-lock.transports.broadcast.channel_prefix', 'filament-resource-lock');
        $modelHash = sha1($record::class);

        return sprintf('%s.%s.%s', $prefix, $modelHash, $record->getKey());
    }
}
