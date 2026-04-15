<?php

namespace Androsamp\FilamentResourceLock\Models;

use Androsamp\FilamentResourceLock\Models\ResourceLockAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $lockable_type
 * @property int|string $lockable_id
 * @property int|null $user_id
 * @property string|null $session_id
 * @property string|null $lock_cycle_id
 * @property Carbon|null $last_heartbeat_at
 * @property Carbon|null $expires_at
 * @property bool $releasing
 * @property Carbon|null $releasing_expires_at
 * @property int $force_takeover
 * @property int|null $force_takeover_user_id
 * @property string|null $force_takeover_session_id
 * @property array<int, array<string, mixed>>|null $events
 * @property string|null $user_display_name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ResourceLock extends Model
{
    protected $fillable = [
        'lockable_type',
        'lockable_id',
        'user_id',
        'session_id',
        'lock_cycle_id',
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

    public function audits(): HasMany
    {
        return $this->hasMany(ResourceLockAudit::class, 'lock_cycle_id', 'lock_cycle_id');
    }
}
