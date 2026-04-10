<?php

namespace Androsamp\FilamentResourceLock\Concerns;

use Androsamp\FilamentResourceLock\Models\ResourceLock;
use Illuminate\Database\Eloquent\Relations\MorphOne;

trait HasResourceLocks
{
    public function resourceLock(): MorphOne
    {
        return $this->morphOne(ResourceLock::class, 'lockable', 'lockable_type', 'lockable_id');
    }
}
