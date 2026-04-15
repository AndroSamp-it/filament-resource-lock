<?php

namespace Androsamp\FilamentResourceLock\Services;

use Androsamp\FilamentResourceLock\Models\ResourceLock;
use Androsamp\FilamentResourceLock\Support\LockResult;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ResourceLockManager
{
    public function acquireOrRefresh(Model $record, ?int $userId, ?string $sessionId): LockResult
    {
        return $this->isRedisDriver()
            ? $this->acquireOrRefreshFromRedis($record, $userId, $sessionId)
            : $this->acquireOrRefreshFromDatabase($record, $userId, $sessionId);
    }

    /**
     * Returns the active lock for display purposes (e.g. in ResourceLockColumn).
     * Returns null when the lock is expired or in the releasing state past its grace period.
     */
    public function findActiveLock(Model $record): ?ResourceLock
    {
        $lock = $this->find($record);

        if (! $lock) {
            return null;
        }

        $now = CarbonImmutable::now();

        if ($this->isLockExpired($lock, $now)) {
            return null;
        }

        if ($this->isReleasingAndGraceOver($lock, $now)) {
            return null;
        }

        return $lock;
    }

    public function find(Model $record): ?ResourceLock
    {
        if ($this->isRedisDriver()) {
            return $this->findFromRedis($record);
        }

        return ResourceLock::query()
            ->where('lockable_type', $record::class)
            ->where('lockable_id', $record->getKey())
            ->first();
    }

    public function update(Model $record, array $attributes): ?ResourceLock
    {
        if ($this->isRedisDriver()) {
            return $this->mutateRedisLock($record, function (?array $payload) use ($record, $attributes): array {
                $payload ??= $this->emptyPayload($record);

                foreach ($attributes as $key => $value) {
                    $payload[$key] = $value;
                }

                return $payload;
            });
        }

        ResourceLock::query()
            ->where('lockable_type', $record::class)
            ->where('lockable_id', $record->getKey())
            ->update($this->normalizeDatabaseAttributes($attributes));

        return $this->find($record);
    }

    public function setEvents(Model $record, array $events): ?ResourceLock
    {
        return $this->update($record, ['events' => $events]);
    }

    public function appendEvent(Model $record, array $event): ?ResourceLock
    {
        if ($this->isRedisDriver()) {
            return $this->mutateRedisLock($record, function (?array $payload) use ($record, $event): array {
                $payload ??= $this->emptyPayload($record);
                $payload['events'] = is_array($payload['events'] ?? null) ? $payload['events'] : [];
                $payload['events'][] = $event;

                return $payload;
            });
        }

        return DB::transaction(function () use ($record, $event): ?ResourceLock {
            $lock = ResourceLock::query()
                ->where('lockable_type', $record::class)
                ->where('lockable_id', $record->getKey())
                ->lockForUpdate()
                ->first();

            if (! $lock) {
                return null;
            }

            $events = $this->decodeDatabaseEvents($lock->events);
            $events[] = $event;

            $lock->events = $events;
            $lock->save();

            return $lock->fresh(['user']);
        });
    }

    /**
     * Removes the ask_to_unblock event for the given requester after the owner responds.
     */
    public function removeAskToUnblockForRequester(Model $record, ?int $requesterUserId, string $requesterSessionId): ?ResourceLock
    {
        $lock = $this->find($record);

        if (! $lock) {
            return null;
        }

        $events = $this->decodeDatabaseEvents($lock->events);

        $filtered = array_values(array_filter($events, function (array $event) use ($requesterUserId, $requesterSessionId): bool {
            if (($event['event'] ?? '') !== 'ask_to_unblock') {
                return true;
            }

            $sameSession = (string) ($event['session_id'] ?? '') === $requesterSessionId;
            $sameUser = $requesterUserId === null
                || (int) ($event['user_id'] ?? 0) === (int) $requesterUserId;

            return ! ($sameSession && $sameUser);
        }));

        return $this->setEvents($record, $filtered);
    }

    public function release(Model $record, ?int $userId, ?string $sessionId): void
    {
        if ($this->isRedisDriver()) {
            $this->releaseFromRedis($record, $userId, $sessionId);

            return;
        }

        // Only delete the lock owned by this exact session; never touch others.
        ResourceLock::query()
            ->where('lockable_type', $record::class)
            ->where('lockable_id', $record->getKey())
            ->where('user_id', $userId)
            ->where('session_id', $sessionId)
            ->delete();
    }

    public function softRelease(Model $record, ?int $userId, ?string $sessionId): bool
    {
        if (! $this->isRedisDriver()) {
            return $this->softReleaseFromDatabase($record, $userId, $sessionId);
        }

        $graceTtl = (int) config('filament-resource-lock.release_grace_seconds', 3);
        $ignoreStaleSeconds = (int) config('filament-resource-lock.stale_soft_release_ignore_seconds', 2);
        $now = CarbonImmutable::now();
        $released = false;

        $this->mutateRedisLock($record, function (?array $payload) use ($userId, $sessionId, $now, $graceTtl, $ignoreStaleSeconds, &$released): ?array {
            if (! is_array($payload)) {
                return null;
            }

            if (! $this->isOwnedBySession($payload['user_id'] ?? null, $payload['session_id'] ?? null, $userId, $sessionId)) {
                return $payload;
            }

            // A late keepalive from the old tab should not overwrite an active lock
            // that was already reclaimed on a new page (race condition guard).
            if ($ignoreStaleSeconds > 0 && ! empty($payload['last_heartbeat_at'])) {
                $lastHeartbeat = CarbonImmutable::parse($payload['last_heartbeat_at']);
                if ($lastHeartbeat->gt($now->subSeconds($ignoreStaleSeconds))) {
                    return $payload;
                }
            }

            $payload['releasing'] = true;
            $payload['releasing_at'] = $now->toDateTimeString();
            $payload['releasing_expires_at'] = $now->addSeconds($graceTtl)->toDateTimeString();
            $released = true;

            return $payload;
        });

        return $released;
    }

    // -------------------------------------------------------------------------
    // Database acquire / release
    // -------------------------------------------------------------------------

    protected function acquireOrRefreshFromDatabase(Model $record, ?int $userId, ?string $sessionId): LockResult
    {
        $now = CarbonImmutable::now();
        $expiresAt = $now->addSeconds((int) config('filament-resource-lock.ttl_seconds', 30));

        return DB::transaction(function () use ($record, $userId, $sessionId, $now, $expiresAt): LockResult {
            $lock = ResourceLock::query()
                ->where('lockable_type', $record::class)
                ->where('lockable_id', $record->getKey())
                ->lockForUpdate()
                ->first();

            if (! $lock) {
                $lock = ResourceLock::query()->create([
                    'lockable_type'  => $record::class,
                    'lockable_id'    => $record->getKey(),
                    'user_id'        => $userId,
                    'session_id'     => $sessionId,
                    'lock_cycle_id'  => (string) Str::uuid(),
                    'last_heartbeat_at' => $now,
                    'expires_at'     => $expiresAt,
                ]);

                return new LockResult(true, $lock->fresh(['user']));
            }

            $isOwner = $this->isOwnedBySession($lock->user_id, $lock->session_id, $userId, $sessionId);
            $isExpired = $lock->expires_at?->lte($now) ?? true;

            // Two-phase release: the lock is in the releasing state.
            if ($lock->releasing) {
                $graceExpired = $lock->releasing_expires_at?->lte($now) ?? true;

                if ($isOwner) {
                    // Same session returned (e.g. page reload): instant reclaim.
                    return new LockResult(true, $this->refreshDatabaseLock($lock, $userId, $sessionId, $now, $expiresAt));
                }

                if (! $graceExpired) {
                    // Grace period still active; other sessions must wait.
                    return new LockResult(false, $lock->fresh(['user']));
                }

                // Grace period expired: treat the lock as free.
                $isExpired = true;
            }

            if ($isOwner || $isExpired) {
                return new LockResult(true, $this->refreshDatabaseLock($lock, $userId, $sessionId, $now, $expiresAt));
            }

            return new LockResult(false, $lock->fresh(['user']));
        });
    }

    protected function softReleaseFromDatabase(Model $record, ?int $userId, ?string $sessionId): bool
    {
        $graceTtl = (int) config('filament-resource-lock.release_grace_seconds', 3);
        $ignoreStaleSeconds = (int) config('filament-resource-lock.stale_soft_release_ignore_seconds', 2);
        $now = CarbonImmutable::now();
        $released = false;

        DB::transaction(function () use ($record, $userId, $sessionId, $now, $graceTtl, $ignoreStaleSeconds, &$released): void {
            $lock = ResourceLock::query()
                ->where('lockable_type', $record::class)
                ->where('lockable_id', $record->getKey())
                ->where('user_id', $userId)
                ->where('session_id', $sessionId)
                ->lockForUpdate()
                ->first();

            if (! $lock) {
                return;
            }

            // Guard against a stale keepalive overwriting a reclaimed lock.
            if ($ignoreStaleSeconds > 0 && $lock->last_heartbeat_at) {
                if ($lock->last_heartbeat_at->gt($now->subSeconds($ignoreStaleSeconds))) {
                    return;
                }
            }

            $lock->forceFill([
                'releasing' => true,
                'releasing_expires_at' => $now->addSeconds($graceTtl),
            ])->save();

            $released = true;
        });

        return $released;
    }

    protected function releaseFromRedis(Model $record, ?int $userId, ?string $sessionId): void
    {
        $this->mutateRedisLock($record, function (?array $payload) use ($userId, $sessionId): ?array {
            if (! is_array($payload)) {
                return null;
            }

            $isOwner = $this->isOwnedBySession($payload['user_id'] ?? null, $payload['session_id'] ?? null, $userId, $sessionId);

            return $isOwner ? null : $payload;
        });
    }

    // -------------------------------------------------------------------------
    // Redis acquire
    // -------------------------------------------------------------------------

    protected function acquireOrRefreshFromRedis(Model $record, ?int $userId, ?string $sessionId): LockResult
    {
        $now = CarbonImmutable::now();
        $expiresAt = $now->addSeconds((int) config('filament-resource-lock.ttl_seconds', 30));

        $lock = $this->mutateRedisLock($record, function (?array $payload) use ($record, $userId, $sessionId, $now, $expiresAt): array {
            $payload ??= $this->emptyPayload($record);

            $payloadExpiresAt = isset($payload['expires_at']) ? CarbonImmutable::parse($payload['expires_at']) : null;
            $isExpired = $payloadExpiresAt?->lte($now) ?? true;
            $isOwner = $this->isOwnedBySession($payload['user_id'] ?? null, $payload['session_id'] ?? null, $userId, $sessionId);

            if (! empty($payload['releasing'])) {
                $graceExpiresAt = isset($payload['releasing_expires_at'])
                    ? CarbonImmutable::parse($payload['releasing_expires_at'])
                    : null;
                $graceExpired = $graceExpiresAt?->lte($now) ?? true;

                if ($isOwner) {
                    // Same session returned: reclaim the lock and clear releasing flags.
                    return $this->claimRedisPayload($payload, $userId, $sessionId, $now, $expiresAt);
                }

                if (! $graceExpired) {
                    // Grace period active; other sessions still cannot take over.
                    $payload['_acquired'] = false;

                    return $payload;
                }

                // Grace period expired: the lock is effectively free.
                $isExpired = true;
            }

            if ($isOwner || $isExpired || empty($payload['session_id'])) {
                return $this->claimRedisPayload($payload, $userId, $sessionId, $now, $expiresAt);
            }

            $payload['_acquired'] = false;

            return $payload;
        });

        if (! $lock) {
            return new LockResult(false, null);
        }

        $payload = $lock->getAttribute('_payload') ?? [];

        return new LockResult((bool) ($payload['_acquired'] ?? false), $lock);
    }

    // -------------------------------------------------------------------------
    // Redis helpers
    // -------------------------------------------------------------------------

    protected function findFromRedis(Model $record): ?ResourceLock
    {
        $payload = $this->cacheStore()->get($this->redisDataKey($record));

        return is_array($payload) ? $this->toResourceLock($payload) : null;
    }

    protected function mutateRedisLock(Model $record, callable $mutator): ?ResourceLock
    {
        /** @var \Illuminate\Contracts\Cache\Repository&\Illuminate\Contracts\Cache\LockProvider $store */
        $store = $this->cacheStore();
        $mutex = $store->lock($this->redisMutexKey($record), 5);

        if (! $mutex) {
            throw new RuntimeException('Failed to obtain Redis lock mutex.');
        }

        return $mutex->block(3, function () use ($record, $mutator): ?ResourceLock {
            /** @var \Illuminate\Contracts\Cache\Repository $store */
            $store = $this->cacheStore();
            $dataKey = $this->redisDataKey($record);
            $current = $store->get($dataKey);
            $current = is_array($current) ? $current : null;

            $next = $mutator($current);

            if (! is_array($next)) {
                $store->forget($dataKey);

                return null;
            }

            $ttl = max((int) config('filament-resource-lock.ttl_seconds', 30) * 3, 60);
            $store->put($dataKey, $next, $ttl);

            return $this->toResourceLock($next);
        });
    }

    protected function toResourceLock(array $payload): ResourceLock
    {
        $lock = new ResourceLock();
        $lock->forceFill([
            'lockable_type'             => $payload['lockable_type'] ?? null,
            'lockable_id'               => $payload['lockable_id'] ?? null,
            'user_id'                   => $payload['user_id'] ?? null,
            'session_id'                => $payload['session_id'] ?? null,
            'lock_cycle_id'             => $payload['lock_cycle_id'] ?? null,
            'last_heartbeat_at'         => $payload['last_heartbeat_at'] ?? null,
            'expires_at'                => $payload['expires_at'] ?? null,
            'force_takeover'            => $payload['force_takeover'] ?? 0,
            'force_takeover_user_id'    => $payload['force_takeover_user_id'] ?? null,
            'force_takeover_session_id' => $payload['force_takeover_session_id'] ?? null,
            'events'                    => $payload['events'] ?? [],
            'user_display_name'         => $payload['user_display_name'] ?? null,
        ]);
        $lock->setAttribute('_payload', $payload);

        return $lock;
    }

    protected function emptyPayload(Model $record): array
    {
        return [
            'lockable_type' => $record::class,
            'lockable_id' => $record->getKey(),
            'user_id' => null,
            'session_id' => null,
            'last_heartbeat_at' => null,
            'expires_at' => null,
            'force_takeover' => 0,
            'force_takeover_user_id' => null,
            'force_takeover_session_id' => null,
            'user_display_name' => null,
            'events' => [],
        ];
    }

    // -------------------------------------------------------------------------
    // Shared utilities
    // -------------------------------------------------------------------------

    /**
     * Claims a Redis payload for the given session, clearing any releasing flags.
     * A new lock_cycle_id is issued when the owning session changes.
     */
    private function claimRedisPayload(array $payload, ?int $userId, ?string $sessionId, CarbonImmutable $now, CarbonImmutable $expiresAt): array
    {
        $ownerChanged = (string) ($payload['session_id'] ?? '') !== (string) ($sessionId ?? '');

        unset($payload['releasing'], $payload['releasing_at'], $payload['releasing_expires_at']);

        return array_merge($payload, [
            'user_id'           => $userId,
            'session_id'        => $sessionId,
            'lock_cycle_id'     => $ownerChanged ? (string) Str::uuid() : ($payload['lock_cycle_id'] ?? (string) Str::uuid()),
            'last_heartbeat_at' => $now->toDateTimeString(),
            'expires_at'        => $expiresAt->toDateTimeString(),
            'user_display_name' => $this->resolveCurrentUserDisplayName(),
            '_acquired'         => true,
        ]);
    }

    /**
     * Refreshes a database lock record for the given session, clearing any releasing flags.
     * A new lock_cycle_id is generated whenever the owning session changes.
     */
    private function refreshDatabaseLock(ResourceLock $lock, ?int $userId, ?string $sessionId, CarbonImmutable $now, CarbonImmutable $expiresAt): ResourceLock
    {
        $ownerChanged = (string) ($lock->session_id ?? '') !== (string) ($sessionId ?? '');

        $lock->forceFill([
            'user_id'             => $userId,
            'session_id'          => $sessionId,
            'lock_cycle_id'       => $ownerChanged ? (string) Str::uuid() : ($lock->lock_cycle_id ?? (string) Str::uuid()),
            'last_heartbeat_at'   => $now,
            'expires_at'          => $expiresAt,
            'releasing'           => false,
            'releasing_expires_at' => null,
        ])->save();

        return $lock->fresh(['user']);
    }

    private function isOwnedBySession(mixed $lockUserId, mixed $lockSessionId, ?int $userId, ?string $sessionId): bool
    {
        return (int) ($lockUserId ?? 0) === (int) $userId
            && (string) ($lockSessionId ?? '') === (string) ($sessionId ?? '');
    }

    private function isLockExpired(ResourceLock $lock, CarbonImmutable $now): bool
    {
        return $lock->expires_at !== null && CarbonImmutable::parse($lock->expires_at)->lte($now);
    }

    /**
     * Returns true when the lock is in the releasing state and its grace period has passed.
     * At that point the lock is effectively free for others to acquire.
     */
    private function isReleasingAndGraceOver(ResourceLock $lock, CarbonImmutable $now): bool
    {
        $payload = $lock->getAttribute('_payload') ?? [];

        if (! empty($payload['releasing'])) {
            // Redis mode.
            $graceExpiresAt = isset($payload['releasing_expires_at'])
                ? CarbonImmutable::parse($payload['releasing_expires_at'])
                : null;
        } elseif ($lock->releasing) {
            // Database mode.
            $graceExpiresAt = $lock->releasing_expires_at
                ? CarbonImmutable::parse($lock->releasing_expires_at)
                : null;
        } else {
            return false;
        }

        return ! isset($graceExpiresAt) || $graceExpiresAt->lte($now);
    }

    protected function getStorageDriver(): string
    {
        return (string) config('filament-resource-lock.storage.driver', 'database');
    }

    private function isRedisDriver(): bool
    {
        return $this->getStorageDriver() === 'redis';
    }

    protected function redisDataKey(Model $record): string
    {
        $prefix = (string) config('filament-resource-lock.storage.redis.prefix', 'filament-resource-lock');

        return sprintf('%s:data:%s:%s', $prefix, str_replace('\\', '.', $record::class), $record->getKey());
    }

    protected function redisMutexKey(Model $record): string
    {
        $prefix = (string) config('filament-resource-lock.storage.redis.prefix', 'filament-resource-lock');

        return sprintf('%s:mutex:%s:%s', $prefix, str_replace('\\', '.', $record::class), $record->getKey());
    }

    protected function cacheStore()
    {
        return Cache::store((string) config('filament-resource-lock.storage.redis.store', 'redis'));
    }

    protected function normalizeDatabaseAttributes(array $attributes): array
    {
        if (array_key_exists('events', $attributes) && is_array($attributes['events'])) {
            $attributes['events'] = json_encode($attributes['events'], JSON_UNESCAPED_UNICODE);
        }

        return $attributes;
    }

    private function decodeDatabaseEvents(mixed $events): array
    {
        if (is_string($events)) {
            return json_decode($events, true) ?? [];
        }

        return is_array($events) ? $events : [];
    }

    protected function resolveCurrentUserDisplayName(): ?string
    {
        $user = auth()->user();

        if (! $user) {
            return null;
        }

        $column = (string) config('filament-resource-lock.user_display_column', 'name');

        return (string) ($user->{$column} ?? $user->getKey());
    }
}
