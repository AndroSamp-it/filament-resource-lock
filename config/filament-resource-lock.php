<?php

return [
    // Database table name for storing active locks.
    'table' => 'resource_locks',

    // How the client receives lock state updates. Options: heartbeat, broadcast.
    'update_driver' => 'heartbeat',

    // Transport-specific settings.
    'transports' => [
        'heartbeat' => [
            // How often Livewire polls for lock state changes (seconds).
            'heartbeat_seconds' => 10,
        ],
        'broadcast' => [
            // Private channel name prefix: {prefix}.{modelHash}.{id}
            'channel_prefix' => 'filament-resource-lock',
            // Broadcast event name.
            'event' => 'resource-lock.updated',
            // How often the lock is renewed while the Echo channel is open (seconds).
            // null = max(5, floor(ttl_seconds * 0.4)) — slightly faster than half the TTL.
            'renew_interval_seconds' => 15,
        ],
    ],

    // Lock storage backend, independent of the update driver. Options: database, redis.
    'storage' => [
        'driver' => 'database',
        'redis' => [
            'store' => 'redis',
            'prefix' => 'filament-resource-lock',
        ],
    ],

    // Lock TTL: if no heartbeat arrives within this many seconds, the lock expires.
    'ttl_seconds' => 20,

    // Grace period (seconds) used in broadcast mode. After a page unload the lock
    // is marked as "releasing" and held for this duration, allowing a reload of the
    // same session to instantly reclaim it. Waiting sessions receive a heartbeat
    // once the grace period plus a small buffer has elapsed.
    'release_grace_seconds' => 3,

    // Ignore soft-release requests if a heartbeat arrived within this many seconds.
    // Prevents a stale keepalive from the old tab from overwriting a lock that was
    // already reclaimed on a freshly opened page.
    'stale_soft_release_ignore_seconds' => 2,

    // User model that owns the lock.
    'user_model' => \App\Models\User::class,

    // User model attribute shown in lock-related UI (modals, tooltips).
    'user_display_column' => 'name',

    // Permissions for the resource lock actions.
    'permission' => [
        'save_and_unlock' => [
            'enabled' => true,
            'permission' => 'filament-resource-lock.save_and_unlock', // null = no permission required
        ],
        'ask_to_unblock' => [
            'enabled' => true,
            'permission' => 'filament-resource-lock.ask_to_unblock', // null = no permission required
        ],
    ],

    'audit' => [
        'enabled' => false,
        'table' => 'resource_lock_audits',
        'max_entries_per_resource' => 500,
    ],
];
