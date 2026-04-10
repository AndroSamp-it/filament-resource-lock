<?php

namespace Androsamp\FilamentResourceLock\Tests\Feature;

use Androsamp\FilamentResourceLock\Services\ResourceLockManager;
use Androsamp\FilamentResourceLock\Tests\Fixtures\TestModel;
use Androsamp\FilamentResourceLock\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class AcquireLockTest extends TestCase
{
    use RefreshDatabase;

    private ResourceLockManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = app(ResourceLockManager::class);
    }

    public function test_first_session_acquires_lock(): void
    {
        $record = TestModel::create(['name' => 'Post']);

        $result = $this->manager->acquireOrRefresh($record, userId: 1, sessionId: 'sess-a');

        $this->assertTrue($result->acquired);
        $this->assertNotNull($result->lock);
        $this->assertEquals(1, $result->lock->user_id);
        $this->assertEquals('sess-a', $result->lock->session_id);
    }

    public function test_second_session_cannot_acquire_active_lock(): void
    {
        $record = TestModel::create(['name' => 'Post']);

        $this->manager->acquireOrRefresh($record, userId: 1, sessionId: 'sess-a');
        $result = $this->manager->acquireOrRefresh($record, userId: 2, sessionId: 'sess-b');

        $this->assertFalse($result->acquired);
        $this->assertEquals('sess-a', $result->lock?->session_id);
    }

    public function test_same_session_refreshes_existing_lock(): void
    {
        $record = TestModel::create(['name' => 'Post']);

        $first = $this->manager->acquireOrRefresh($record, userId: 1, sessionId: 'sess-a');
        $second = $this->manager->acquireOrRefresh($record, userId: 1, sessionId: 'sess-a');

        $this->assertTrue($second->acquired);
        $this->assertEquals($first->lock?->getKey(), $second->lock?->getKey());
    }

    public function test_expired_lock_can_be_acquired_by_new_session(): void
    {
        $record = TestModel::create(['name' => 'Post']);

        $this->manager->acquireOrRefresh($record, userId: 1, sessionId: 'sess-a');

        DB::table('resource_locks')
            ->where('lockable_id', $record->getKey())
            ->update(['expires_at' => now()->subMinutes(5)]);

        $result = $this->manager->acquireOrRefresh($record, userId: 2, sessionId: 'sess-b');

        $this->assertTrue($result->acquired);
        $this->assertEquals('sess-b', $result->lock?->session_id);
    }

    public function test_two_different_records_can_be_locked_independently(): void
    {
        $a = TestModel::create(['name' => 'A']);
        $b = TestModel::create(['name' => 'B']);

        $resultA = $this->manager->acquireOrRefresh($a, userId: 1, sessionId: 'sess-a');
        $resultB = $this->manager->acquireOrRefresh($b, userId: 2, sessionId: 'sess-b');

        $this->assertTrue($resultA->acquired);
        $this->assertTrue($resultB->acquired);
    }

    public function test_lock_row_is_created_in_database(): void
    {
        $record = TestModel::create(['name' => 'Post']);

        $this->manager->acquireOrRefresh($record, userId: 1, sessionId: 'sess-a');

        $this->assertDatabaseHas('resource_locks', [
            'lockable_type' => TestModel::class,
            'lockable_id'   => $record->getKey(),
            'user_id'       => 1,
            'session_id'    => 'sess-a',
        ]);
    }
}
