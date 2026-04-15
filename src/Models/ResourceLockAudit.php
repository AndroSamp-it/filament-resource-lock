<?php

namespace Androsamp\FilamentResourceLock\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int                        $id
 * @property string                     $lockable_type
 * @property int|string                 $lockable_id
 * @property string|null                $lock_cycle_id
 * @property int|null                   $version
 * @property string                     $event
 * @property int|null                   $actor_user_id
 * @property string|null                $actor_session_id
 * @property string|null                $actor_display_name
 * @property array<string, mixed>|null  $payload
 * @property array<string, mixed>|null  $snapshot
 * @property array<int, array<string, mixed>>|null $changes
 * @property Carbon|null                $created_at
 * @property Carbon|null                $updated_at
 */
class ResourceLockAudit extends Model
{
    protected $fillable = [
        'lockable_type',
        'lockable_id',
        'lock_cycle_id',
        'version',
        'event',
        'actor_user_id',
        'actor_session_id',
        'actor_display_name',
        'payload',
        'snapshot',
        'changes',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable((string) config('filament-resource-lock.audit.table', 'resource_lock_audits'));
    }

    protected function casts(): array
    {
        return [
            'payload'  => 'array',
            'snapshot' => 'array',
            'changes'  => 'array',
        ];
    }

    public function actor(): BelongsTo
    {
        $userModel = config('filament-resource-lock.user_model', \App\Models\User::class);

        return $this->belongsTo($userModel, 'actor_user_id');
    }

    /** Number of changed fields in this snapshot. */
    public function getChangesCountAttribute(): int
    {
        return count($this->changes ?? []);
    }
}
