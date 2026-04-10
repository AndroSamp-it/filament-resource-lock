<?php

namespace Androsamp\FilamentResourceLock\Support;

use Androsamp\FilamentResourceLock\Models\ResourceLock;

/**
 * Value object returned by ResourceLockManager::acquireOrRefresh().
 * When acquired is true the current session owns the lock; otherwise lock
 * belongs to whoever currently holds it (or is null on a storage failure).
 */
final class LockResult
{
    public function __construct(
        public readonly bool $acquired,
        public readonly ?ResourceLock $lock,
    ) {}
}
