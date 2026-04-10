<?php

namespace Androsamp\FilamentResourceLock\Tests\Feature;

use Androsamp\FilamentResourceLock\Services\ResourceLockManager;
use Androsamp\FilamentResourceLock\Tests\Fixtures\TestModel;
use Androsamp\FilamentResourceLock\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ReleaseLockTest extends TestCase
{
    use RefreshDatabase;

    private ResourceLockManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = app(ResourceLockManager::class);
    }

    public function test_owner_can_release_lock(): void
    {
        $record = TestModel::create(['name' => 'Post']);

        $this->manager->acquireOrRefresh($record, userId: 1, sessionId: 'sess-a');
        $this->manager->release($record, userId: 1, sessionId: 'sess-a');

        $this->assertNull($this->manager->find($record));
    }

    public function test_release_does_not_affect_another_sessions_lock(): void
    {
        $record = TestModel::create(['name' => 'Post']);

        $this->manager->acquireOrRefresh($record, userId: 1, sessionId: 'sess-a');
        $this->manager->release($record, userId: 2, sessionId: 'sess-b');

        $lock = $this->manager->find($record);
        $this->assertNotNull($lock);
        $this->assertEquals('sess-a', $lock->session_id);
    }

    public function test_after_release_new_session_can_acquire(): void
    {
        $record = TestModel::create(['name' => 'Post']);

        $this->manager->acquireOrRefresh($record, userId: 1, sessionId: 'sess-a');
        $this->manager->release($record, userId: 1, sessionId: 'sess-a');

        $result = $this->manager->acquireOrRefresh($record, userId: 2, sessionId: 'sess-b');

        $this->assertTrue($result->acquired);
        $this->assertEquals('sess-b', $result->lock?->session_id);
    }

    public function test_releasing_non_existent_lock_does_not_throw(): void
    {
        $record = TestModel::create(['name' => 'Post']);

        // Should silently do nothing.
        $this->manager->release($record, userId: 1, sessionId: 'sess-a');

        $this->assertNull($this->manager->find($record));
    }
}
