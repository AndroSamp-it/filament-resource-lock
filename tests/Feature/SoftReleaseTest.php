<?php

namespace Androsamp\FilamentResourceLock\Tests\Feature;

use Androsamp\FilamentResourceLock\Services\ResourceLockManager;
use Androsamp\FilamentResourceLock\Tests\Fixtures\TestModel;
use Androsamp\FilamentResourceLock\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class SoftReleaseTest extends TestCase
{
    use RefreshDatabase;

    private ResourceLockManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = app(ResourceLockManager::class);
    }

    public function test_soft_release_marks_lock_as_releasing(): void
    {
        $record = TestModel::create(['name' => 'Post']);

        $this->manager->acquireOrRefresh($record, userId: 1, sessionId: 'sess-a');
        $released = $this->manager->softRelease($record, userId: 1, sessionId: 'sess-a');

        $this->assertTrue($released);

        $lock = $this->manager->find($record);
        $this->assertNotNull($lock);
        $this->assertTrue((bool) $lock->releasing);
    }

    public function test_same_session_can_reclaim_lock_during_grace_period(): void
    {
        $record = TestModel::create(['name' => 'Post']);

        $this->manager->acquireOrRefresh($record, userId: 1, sessionId: 'sess-a');
        $this->manager->softRelease($record, userId: 1, sessionId: 'sess-a');

        $result = $this->manager->acquireOrRefresh($record, userId: 1, sessionId: 'sess-a');

        $this->assertTrue($result->acquired);
        $this->assertFalse((bool) $result->lock?->releasing);
    }

    public function test_other_session_cannot_acquire_during_grace_period(): void
    {
        config(['filament-resource-lock.release_grace_seconds' => 60]);
        $record = TestModel::create(['name' => 'Post']);

        $this->manager->acquireOrRefresh($record, userId: 1, sessionId: 'sess-a');
        $this->manager->softRelease($record, userId: 1, sessionId: 'sess-a');

        $result = $this->manager->acquireOrRefresh($record, userId: 2, sessionId: 'sess-b');

        $this->assertFalse($result->acquired);
    }

    public function test_other_session_acquires_after_grace_period_expires(): void
    {
        $record = TestModel::create(['name' => 'Post']);

        $this->manager->acquireOrRefresh($record, userId: 1, sessionId: 'sess-a');
        $this->manager->softRelease($record, userId: 1, sessionId: 'sess-a');

        DB::table('resource_locks')
            ->where('lockable_id', $record->getKey())
            ->update(['releasing_expires_at' => now()->subMinutes(1)]);

        $result = $this->manager->acquireOrRefresh($record, userId: 2, sessionId: 'sess-b');

        $this->assertTrue($result->acquired);
        $this->assertEquals('sess-b', $result->lock?->session_id);
    }

    public function test_soft_release_by_wrong_session_returns_false(): void
    {
        $record = TestModel::create(['name' => 'Post']);

        $this->manager->acquireOrRefresh($record, userId: 1, sessionId: 'sess-a');
        $released = $this->manager->softRelease($record, userId: 2, sessionId: 'sess-b');

        $this->assertFalse($released);

        $lock = $this->manager->find($record);
        $this->assertFalse((bool) $lock?->releasing);
    }
}
