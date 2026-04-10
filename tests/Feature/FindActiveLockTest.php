<?php

namespace Androsamp\FilamentResourceLock\Tests\Feature;

use Androsamp\FilamentResourceLock\Services\ResourceLockManager;
use Androsamp\FilamentResourceLock\Tests\Fixtures\TestModel;
use Androsamp\FilamentResourceLock\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class FindActiveLockTest extends TestCase
{
    use RefreshDatabase;

    private ResourceLockManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = app(ResourceLockManager::class);
    }

    public function test_returns_active_lock(): void
    {
        $record = TestModel::create(['name' => 'Post']);
        $this->manager->acquireOrRefresh($record, userId: 1, sessionId: 'sess-a');

        $lock = $this->manager->findActiveLock($record);

        $this->assertNotNull($lock);
        $this->assertEquals(1, $lock->user_id);
    }

    public function test_returns_null_when_no_lock_exists(): void
    {
        $record = TestModel::create(['name' => 'Post']);

        $this->assertNull($this->manager->findActiveLock($record));
    }

    public function test_returns_null_for_expired_lock(): void
    {
        $record = TestModel::create(['name' => 'Post']);
        $this->manager->acquireOrRefresh($record, userId: 1, sessionId: 'sess-a');

        DB::table('resource_locks')
            ->where('lockable_id', $record->getKey())
            ->update(['expires_at' => now()->subMinutes(5)]);

        $this->assertNull($this->manager->findActiveLock($record));
    }

    public function test_returns_null_when_releasing_and_grace_expired(): void
    {
        $record = TestModel::create(['name' => 'Post']);
        $this->manager->acquireOrRefresh($record, userId: 1, sessionId: 'sess-a');
        $this->manager->softRelease($record, userId: 1, sessionId: 'sess-a');

        DB::table('resource_locks')
            ->where('lockable_id', $record->getKey())
            ->update(['releasing_expires_at' => now()->subMinutes(1)]);

        $this->assertNull($this->manager->findActiveLock($record));
    }

    public function test_returns_lock_during_active_grace_period(): void
    {
        config(['filament-resource-lock.release_grace_seconds' => 60]);
        $record = TestModel::create(['name' => 'Post']);

        $this->manager->acquireOrRefresh($record, userId: 1, sessionId: 'sess-a');
        $this->manager->softRelease($record, userId: 1, sessionId: 'sess-a');

        // Lock is in releasing state but grace period has not expired.
        $lock = $this->manager->findActiveLock($record);

        $this->assertNotNull($lock);
    }

    public function test_append_and_set_events(): void
    {
        $record = TestModel::create(['name' => 'Post']);
        $this->manager->acquireOrRefresh($record, userId: 1, sessionId: 'sess-a');

        $this->manager->appendEvent($record, ['event' => 'ask_to_unblock', 'user_id' => 2, 'session_id' => 'sess-b']);
        $this->manager->appendEvent($record, ['event' => 'ask_to_unblock', 'user_id' => 3, 'session_id' => 'sess-c']);

        $lock = $this->manager->find($record);
        $events = is_array($lock?->events) ? $lock->events : json_decode((string) $lock?->events, true);

        $this->assertCount(2, $events);

        $this->manager->setEvents($record, []);
        $lock = $this->manager->find($record);
        $events = is_array($lock?->events) ? $lock->events : json_decode((string) $lock?->events, true);

        $this->assertEmpty($events);
    }
}
