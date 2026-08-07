<?php

namespace Androsamp\FilamentResourceLock\Tests\Unit;

use Androsamp\FilamentResourceLock\Models\ResourceLockAudit;
use Androsamp\FilamentResourceLock\Services\ResourceAuditService;
use Androsamp\FilamentResourceLock\Tests\Fixtures\TestModel;
use Androsamp\FilamentResourceLock\Tests\TestCase;

class ResourceAuditCreatedEventTest extends TestCase
{
    public function test_is_first_version_when_no_snapshots_exist(): void
    {
        $record = TestModel::query()->create(['name' => 'Alpha']);
        $service = app(ResourceAuditService::class);

        $this->assertTrue($service->isFirstVersion($record));
    }

    public function test_record_snapshot_can_store_created_event(): void
    {
        $record = TestModel::query()->create(['name' => 'Beta']);
        $service = app(ResourceAuditService::class);

        $snapshot = [
            'name' => [
                'value' => 'Beta',
                'label' => 'Name',
                'type' => 'TextInput',
            ],
        ];

        $changes = $service->computeChanges([], $snapshot);

        $entry = $service->recordSnapshot(
            record: $record,
            lockCycleId: 'manual',
            actorUserId: null,
            actorSessionId: null,
            actorDisplayName: 'Tester',
            snapshot: $snapshot,
            changes: $changes,
            event: 'created',
        );

        $this->assertSame('created', $entry->event);
        $this->assertSame(1, $entry->version);
        $this->assertFalse($service->isFirstVersion($record));

        $this->assertDatabaseHas(config('filament-resource-lock.audit.table', 'resource_lock_audits'), [
            'id' => $entry->id,
            'event' => 'created',
            'version' => 1,
        ]);
    }

    public function test_subsequent_snapshot_defaults_to_saved_event(): void
    {
        $record = TestModel::query()->create(['name' => 'Gamma']);
        $service = app(ResourceAuditService::class);

        $service->recordSnapshot(
            record: $record,
            lockCycleId: 'manual',
            actorUserId: null,
            actorSessionId: null,
            actorDisplayName: 'Tester',
            snapshot: ['name' => ['value' => 'Gamma', 'label' => 'Name', 'type' => 'TextInput']],
            changes: [['field' => 'name', 'label' => 'Name', 'type' => 'TextInput', 'old' => null, 'new' => 'Gamma']],
            event: 'created',
        );

        $entry = $service->recordSnapshot(
            record: $record,
            lockCycleId: 'manual',
            actorUserId: null,
            actorSessionId: null,
            actorDisplayName: 'Tester',
            snapshot: ['name' => ['value' => 'Gamma 2', 'label' => 'Name', 'type' => 'TextInput']],
            changes: [['field' => 'name', 'label' => 'Name', 'type' => 'TextInput', 'old' => 'Gamma', 'new' => 'Gamma 2']],
        );

        $this->assertSame('saved', $entry->event);
        $this->assertSame(2, $entry->version);
        $this->assertSame(2, ResourceLockAudit::query()->where('lockable_id', $record->getKey())->count());
    }

    public function test_get_creator_display_name_from_created_audit(): void
    {
        $record = TestModel::query()->create(['name' => 'Delta']);
        $service = app(ResourceAuditService::class);

        $this->assertNull($service->getCreatorDisplayName($record));

        $service->recordSnapshot(
            record: $record,
            lockCycleId: 'manual',
            actorUserId: null,
            actorSessionId: null,
            actorDisplayName: 'Alice Creator',
            snapshot: ['name' => ['value' => 'Delta', 'label' => 'Name', 'type' => 'TextInput']],
            changes: [['field' => 'name', 'label' => 'Name', 'type' => 'TextInput', 'old' => null, 'new' => 'Delta']],
            event: 'created',
        );

        $this->assertSame('Alice Creator', $service->getCreatorDisplayName($record));
        $this->assertSame('created', $service->getCreationAudit($record)?->event);
    }
}
