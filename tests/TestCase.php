<?php

namespace Androsamp\FilamentResourceLock\Tests;

use Androsamp\FilamentResourceLock\FilamentResourceLockServiceProvider;
use Androsamp\FilamentResourceLock\Tests\Fixtures\TestUser;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            FilamentResourceLockServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        $app['config']->set('broadcasting.default', 'null');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('queue.default', 'sync');
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Override after ServiceProvider::register() has called mergeConfigFrom().
        config([
            'filament-resource-lock.user_model'                        => TestUser::class,
            'filament-resource-lock.storage.driver'                    => 'database',
            'filament-resource-lock.update_driver'                     => 'heartbeat',
            'filament-resource-lock.ttl_seconds'                       => 30,
            'filament-resource-lock.release_grace_seconds'             => 3,
            // Disable the stale-heartbeat guard so soft releases take effect immediately.
            'filament-resource-lock.stale_soft_release_ignore_seconds' => 0,
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
    }
}
