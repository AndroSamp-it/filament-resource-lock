<?php

namespace Androsamp\FilamentResourceLock\Services;

use Androsamp\FilamentResourceLock\Models\ResourceLockAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class ResourceAuditService
{
    /**
     * Rolls back selected fields from an audit entry and writes a new snapshot version.
     *
     * @param  array<int, string>  $fields
     */
    public function rollbackFieldsFromAudit(
        ResourceLockAudit $audit,
        array $fields,
        ?int $actorUserId,
        ?string $actorSessionId,
        ?string $actorDisplayName,
    ): ?ResourceLockAudit {
        $selectedFields = array_values(array_unique(array_filter($fields, static fn (mixed $field): bool => is_string($field) && $field !== '')));

        if (empty($selectedFields)) {
            throw new RuntimeException('No fields were selected for rollback.');
        }

        /** @var Model|null $record */
        $record = app($audit->lockable_type)->newQuery()->find($audit->lockable_id);

        if (! $record instanceof Model) {
            throw new RuntimeException('Target resource for rollback was not found.');
        }

        $selectedChanges = collect($audit->changes ?? [])
            ->filter(fn (array $change): bool => in_array((string) ($change['field'] ?? ''), $selectedFields, true))
            ->values();

        if ($selectedChanges->isEmpty()) {
            throw new RuntimeException('Selected fields are not present in the chosen audit entry.');
        }

        return DB::transaction(function () use ($record, $audit, $selectedChanges, $actorUserId, $actorSessionId, $actorDisplayName): ?ResourceLockAudit {
            $beforeSnapshot = $this->getLatestSnapshot($record);
            $afterSnapshot = $beforeSnapshot;

            foreach ($selectedChanges as $change) {
                $field = (string) ($change['field'] ?? '');

                if ($field === '') {
                    continue;
                }

                $oldValue = $change['old'] ?? null;

                if ($this->rollbackBelongsToManyIfApplicable($record, $field, $oldValue)) {
                    $record->unsetRelation($field);
                    $record->load($field);

                    $afterSnapshot[$field] = [
                        'value' => $this->snapshotRelationValueForAudit($record, $field),
                        'label' => $change['label'] ?? $field,
                        'type' => $change['type'] ?? 'TextInput',
                        'customBlocks' => $change['customBlocks'] ?? [],
                        'audit_diff_preview' => $change['old_preview_html'] ?? null,
                    ];
                } else {
                    $record->setAttribute($field, $oldValue);

                    $afterSnapshot[$field] = [
                        'value' => $oldValue,
                        'label' => $change['label'] ?? $field,
                        'type' => $change['type'] ?? 'TextInput',
                        'customBlocks' => $change['customBlocks'] ?? [],
                        'audit_diff_preview' => $change['old_preview_html'] ?? null,
                    ];
                }

                if (! isset($beforeSnapshot[$field])) {
                    $beforeSnapshot[$field] = [
                        'value' => $change['new'] ?? null,
                        'label' => $change['label'] ?? $field,
                        'type' => $change['type'] ?? 'TextInput',
                        'customBlocks' => $change['customBlocks'] ?? [],
                        'audit_diff_preview' => $change['new_preview_html'] ?? null,
                    ];
                }
            }

            $record->save();

            $changes = $this->computeChanges($beforeSnapshot, $afterSnapshot);

            if (empty($changes)) {
                return null;
            }

            return $this->recordSnapshot(
                record: $record,
                lockCycleId: $this->getCurrentLockCycleId($record) ?? ($audit->lock_cycle_id ?? 'manual'),
                actorUserId: $actorUserId,
                actorSessionId: $actorSessionId,
                actorDisplayName: $actorDisplayName,
                snapshot: $afterSnapshot,
                changes: $changes,
            );
        });
    }

    /**
     * Records a snapshot audit entry (called on form save).
     *
     * @param  array<string, mixed>  $snapshot  Full form snapshot: field => {value, label, type}
     * @param  array<int, array<string, mixed>>  $changes  Changed fields: [{field, label, type, old, new, old_preview_html?, new_preview_html?, ...}]
     */
    public function recordSnapshot(
        Model $record,
        string $lockCycleId,
        ?int $actorUserId,
        ?string $actorSessionId,
        ?string $actorDisplayName,
        array $snapshot,
        array $changes,
    ): ResourceLockAudit {
        $version = $this->nextVersion($record);

        $entry = ResourceLockAudit::create([
            'lockable_type'      => $record::class,
            'lockable_id'        => $record->getKey(),
            'lock_cycle_id'      => $lockCycleId,
            'version'            => $version,
            'event'              => 'saved',
            'actor_user_id'      => $actorUserId,
            'actor_session_id'   => $actorSessionId,
            'actor_display_name' => $actorDisplayName,
            'snapshot'           => $snapshot,
            'changes'            => $changes,
        ]);

        $this->pruneIfNeeded($record);

        return $entry;
    }

    /**
     * Records a lock lifecycle event (acquired, released, force_takeover, etc.).
     *
     * @param  array<string, mixed>  $payload
     */
    public function recordEvent(
        Model $record,
        string $event,
        ?string $lockCycleId,
        ?int $actorUserId,
        ?string $actorSessionId,
        ?string $actorDisplayName,
        array $payload = [],
    ): ResourceLockAudit {
        return ResourceLockAudit::create([
            'lockable_type'      => $record::class,
            'lockable_id'        => $record->getKey(),
            'lock_cycle_id'      => $lockCycleId,
            'version'            => null,
            'event'              => $event,
            'actor_user_id'      => $actorUserId,
            'actor_session_id'   => $actorSessionId,
            'actor_display_name' => $actorDisplayName,
            'payload'            => $payload ?: null,
        ]);
    }

    /**
     * Computes changes between two snapshots.
     *
     * @param  array<string, mixed>  $oldSnapshot
     * @param  array<string, mixed>  $newSnapshot
     * @return array<int, array<string, mixed>>
     */
    public function computeChanges(array $oldSnapshot, array $newSnapshot): array
    {
        $changes = [];

        $allFields = array_unique(array_merge(array_keys($oldSnapshot), array_keys($newSnapshot)));

        foreach ($allFields as $field) {
            $oldEntry = $oldSnapshot[$field] ?? null;
            $newEntry = $newSnapshot[$field] ?? null;

            $oldValue = $oldEntry['value'] ?? null;
            $newValue = $newEntry['value'] ?? null;

            // Normalise for comparison: arrays → JSON string.
            $oldNorm = is_array($oldValue) ? json_encode($oldValue, JSON_UNESCAPED_UNICODE) : (string) ($oldValue ?? '');
            $newNorm = is_array($newValue) ? json_encode($newValue, JSON_UNESCAPED_UNICODE) : (string) ($newValue ?? '');

            if ($oldNorm === $newNorm) {
                continue;
            }

            $changes[] = [
                'field' => $field,
                'label' => $newEntry['label'] ?? $oldEntry['label'] ?? $field,
                'type'  => $newEntry['type'] ?? $oldEntry['type'] ?? 'TextInput',
                'customBlocks' => $newEntry['customBlocks'] ?? $oldEntry['customBlocks'] ?? [],
                'old_display' => $oldEntry['display'] ?? null,
                'new_display' => $newEntry['display'] ?? null,
                'old_preview_html' => is_array($oldEntry) ? ($oldEntry['audit_diff_preview'] ?? null) : null,
                'new_preview_html' => is_array($newEntry) ? ($newEntry['audit_diff_preview'] ?? null) : null,
                'old'   => $oldValue,
                'new'   => $newValue,
            ];
        }

        return $changes;
    }

    /**
     * Returns audit entries for a resource, ordered newest first.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, ResourceLockAudit>
     */
    public function getHistory(Model $record, int $limit = 100): \Illuminate\Database\Eloquent\Collection
    {
        return ResourceLockAudit::query()
            ->where('lockable_type', $record::class)
            ->where('lockable_id', $record->getKey())
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Returns latest saved snapshot for a resource.
     *
     * @return array<string, mixed>
     */
    public function getLatestSnapshot(Model $record): array
    {
        /** @var ResourceLockAudit|null $entry */
        $entry = ResourceLockAudit::query()
            ->where('lockable_type', $record::class)
            ->where('lockable_id', $record->getKey())
            ->whereNotNull('snapshot')
            ->whereNotNull('version')
            ->orderByDesc('version')
            ->first();

        return is_array($entry?->snapshot) ? $entry->snapshot : [];
    }

    /** Removes the oldest entries when the per-resource limit is exceeded. */
    public function pruneIfNeeded(Model $record): void
    {
        $max = (int) config('filament-resource-lock.audit.max_entries_per_resource', 500);

        if ($max <= 0) {
            return;
        }

        $table     = config('filament-resource-lock.audit.table', 'resource_lock_audits');
        $condition = [['lockable_type', $record::class], ['lockable_id', $record->getKey()]];

        $count = DB::table($table)->where($condition)->count();

        if ($count <= $max) {
            return;
        }

        $deleteCount = $count - $max;
        $ids = DB::table($table)
            ->where($condition)
            ->orderBy('id')
            ->limit($deleteCount)
            ->pluck('id');

        DB::table($table)->whereIn('id', $ids)->delete();
    }

    private function nextVersion(Model $record): int
    {
        $table = config('filament-resource-lock.audit.table', 'resource_lock_audits');

        $max = DB::table($table)
            ->where('lockable_type', $record::class)
            ->where('lockable_id', $record->getKey())
            ->max('version');

        return (int) $max + 1;
    }

    /** Finds the lock_cycle_id currently assigned to this record's active lock. */
    public function getCurrentLockCycleId(Model $record): ?string
    {
        $table = config('filament-resource-lock.table', 'resource_locks');

        return DB::table($table)
            ->where('lockable_type', $record::class)
            ->where('lockable_id', $record->getKey())
            ->value('lock_cycle_id');
    }

    /**
     * Filament MultiSelect on a relationship persists via BelongsToMany::sync(), not as a DB column.
     * Rolling back must sync the pivot table using IDs from the audit snapshot.
     */
    private function rollbackBelongsToManyIfApplicable(Model $record, string $field, mixed $oldValue): bool
    {
        if (! method_exists($record, $field)) {
            return false;
        }

        try {
            $relation = $record->{$field}();
        } catch (Throwable) {
            return false;
        }

        if (! $relation instanceof BelongsToMany) {
            return false;
        }

        $keys = $this->extractManyToManyRelatedKeys($oldValue, $relation);
        $relation->sync($keys);

        return true;
    }

    /**
     * @return array<int|string>
     */
    private function extractManyToManyRelatedKeys(mixed $oldValue, BelongsToMany $relation): array
    {
        if ($oldValue === null || $oldValue === '') {
            return [];
        }

        if (! is_array($oldValue)) {
            return [];
        }

        $relatedKeyName = $relation->getRelated()->getKeyName();

        if ($oldValue === []) {
            return [];
        }

        if (array_is_list($oldValue)) {
            $allScalar = true;

            foreach ($oldValue as $v) {
                if ($v !== null && ! is_scalar($v)) {
                    $allScalar = false;

                    break;
                }
            }

            if ($allScalar) {
                return array_values(array_filter(
                    $oldValue,
                    static fn (mixed $v): bool => $v !== null && $v !== '',
                ));
            }

            $keys = [];

            foreach ($oldValue as $item) {
                if (is_array($item) && array_key_exists($relatedKeyName, $item)) {
                    $keys[] = $item[$relatedKeyName];
                } elseif (is_object($item) && isset($item->{$relatedKeyName})) {
                    $keys[] = $item->{$relatedKeyName};
                }
            }

            return array_values(array_filter(
                $keys,
                static fn (mixed $v): bool => $v !== null && $v !== '',
            ));
        }

        if (array_key_exists($relatedKeyName, $oldValue)) {
            $id = $oldValue[$relatedKeyName];

            return ($id !== null && $id !== '') ? [$id] : [];
        }

        return [];
    }

    private function snapshotRelationValueForAudit(Model $record, string $field): mixed
    {
        $value = $record->getRelationValue($field);

        if ($value instanceof Collection) {
            return $value->map(function (mixed $item): mixed {
                if ($item instanceof Model) {
                    $arr = $item->toArray();
                    unset($arr['pivot']);

                    return $arr;
                }

                return $item;
            })->values()->all();
        }

        return $value;
    }
}
