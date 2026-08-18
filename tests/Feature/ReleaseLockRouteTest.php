<?php

namespace Androsamp\FilamentResourceLock\Tests\Feature;

use Androsamp\FilamentResourceLock\Services\ResourceLockManager;
use Androsamp\FilamentResourceLock\Tests\Fixtures\TestModel;
use Androsamp\FilamentResourceLock\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;

class ReleaseLockRouteTest extends TestCase
{
    use RefreshDatabase;

    private ResourceLockManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = app(ResourceLockManager::class);
    }

    public function test_route_is_registered_with_expected_uri_name_and_middleware(): void
    {
        $route = app('router')->getRoutes()->getByName('filament-resource-lock.release');

        $this->assertNotNull($route);
        $this->assertEquals('filament-resource-lock/release', $route->uri());
        $this->assertContains('GET', $route->methods());
        $this->assertContains('HEAD', $route->methods());
        $this->assertContains('web', $route->gatherMiddleware());
        $this->assertStringContainsString('signed', implode('|', $route->middleware()));
    }

    public function test_valid_signed_request_soft_releases_lock_and_returns_ok(): void
    {
        $record = TestModel::create(['name' => 'Post']);
        $this->manager->acquireOrRefresh($record, userId: 1, sessionId: 'sess-a');

        $url = URL::signedRoute('filament-resource-lock.release', [
            'lockable_type' => TestModel::class,
            'lockable_id'   => $record->getKey(),
            'session_id'    => 'sess-a',
            'user_id'       => 1,
        ]);

        $this->getJson($url)
            ->assertOk()
            ->assertJson(['ok' => true]);

        // Soft release marks the lock as releasing (grace period still active),
        // so the same session can instantly reclaim it on page reload.
        $lock = $this->manager->find($record);
        $this->assertNotNull($lock);
        $this->assertTrue($lock->releasing);
    }

    public function test_invalid_lockable_type_returns_422(): void
    {
        $url = URL::signedRoute('filament-resource-lock.release', [
            'lockable_type' => 'App\\Models\\NonExistentModel',
            'lockable_id'   => 1,
            'session_id'    => 'sess-a',
        ]);

        $this->getJson($url)
            ->assertStatus(422)
            ->assertJson(['ok' => false]);
    }

    public function test_non_model_lockable_type_returns_422(): void
    {
        $url = URL::signedRoute('filament-resource-lock.release', [
            'lockable_type' => \stdClass::class,
            'lockable_id'   => 1,
            'session_id'    => 'sess-a',
        ]);

        $this->getJson($url)
            ->assertStatus(422)
            ->assertJson(['ok' => false]);
    }

    public function test_missing_session_id_returns_422(): void
    {
        $url = URL::signedRoute('filament-resource-lock.release', [
            'lockable_type' => TestModel::class,
            'lockable_id'   => 1,
        ]);

        $this->getJson($url)
            ->assertStatus(422)
            ->assertJson(['ok' => false]);
    }

    public function test_unsigned_request_is_rejected_by_signed_middleware(): void
    {
        $this->getJson('/filament-resource-lock/release?lockable_type='.urlencode(TestModel::class).'&lockable_id=1&session_id=sess-a')
            ->assertStatus(403);
    }
}
