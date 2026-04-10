<?php

namespace Androsamp\FilamentResourceLock\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ResourceLock extends Model
{
    protected $fillable = [
        'lockable_type',
        'lockable_id',
        'user_id',
        'session_id',
        'last_heartbeat_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'last_heartbeat_at' => 'datetime',
            'expires_at' => 'datetime',
            'releasing' => 'boolean',
            'releasing_expires_at' => 'datetime',
            'force_takeover' => 'integer',
            'events' => 'array',
        ];
    }

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        // Allow the table name to be overridden via config.
        $this->setTable((string) config('filament-resource-lock.table', 'resource_locks'));
    }

    public function user(): BelongsTo
    {
        $userModel = config('filament-resource-lock.user_model', \App\Models\User::class);

        return $this->belongsTo($userModel, 'user_id');
    }

    public function lockable(): MorphTo
    {
        return $this->morphTo('lockable', 'lockable_type', 'lockable_id');
    }
}
