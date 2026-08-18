<?php

namespace Androsamp\FilamentResourceLock;

use Androsamp\FilamentResourceLock\Commands\InstallFilamentResourceLockCommand;
use Androsamp\FilamentResourceLock\Http\Controllers\ReleaseLockController;
use Androsamp\FilamentResourceLock\Http\Livewire\AuditDiffRichTextSnapshots;
use Androsamp\FilamentResourceLock\Http\Livewire\ResourceAuditHistoryTable;
use Androsamp\FilamentResourceLock\Http\Livewire\ResourceLockObserver;
use Androsamp\FilamentResourceLock\Services\ResourceAuditService;
use Androsamp\FilamentResourceLock\Transports\BroadcastResourceLockUpdateTransport;
use Androsamp\FilamentResourceLock\Transports\HeartbeatResourceLockUpdateTransport;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class FilamentResourceLockServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'filament-resource-lock');
        $this->mergeConfigFrom(__DIR__.'/../config/filament-resource-lock.php', 'filament-resource-lock');

        $this->app->singleton(HeartbeatResourceLockUpdateTransport::class);
        $this->app->singleton(BroadcastResourceLockUpdateTransport::class);
        $this->app->singleton(ResourceLockUpdateDispatcher::class);
        $this->app->singleton(ResourceAuditService::class);
    }

    public function boot(): void
    {
        Livewire::component('resource-lock-observer', ResourceLockObserver::class);
        Livewire::component('resource-lock-audit-history', ResourceAuditHistoryTable::class);
        Livewire::component('resource-lock-audit-diff-rich-text-snapshots', AuditDiffRichTextSnapshots::class);

        $this->registerBroadcastChannel();
        $this->registerReleaseRoute();
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'filament-resource-lock');

        $this->publishes([
            __DIR__.'/../config/filament-resource-lock.php' => config_path('filament-resource-lock.php'),
        ], 'filament-resource-lock-config');

        $this->publishes([
            __DIR__.'/../database/migrations/2026_03_24_000000_create_resource_locks_table.php' => database_path('migrations/2026_03_24_000000_create_resource_locks_table.php'),
            __DIR__.'/../database/migrations/2026_04_14_000000_create_resource_audit_table.php' => database_path('migrations/2026_04_14_000001_create_resource_lock_audits_table.php'),
            __DIR__.'/../database/migrations/2026_04_14_000001_enhance_resource_lock_audits_table.php' => database_path('migrations/2026_04_14_000002_enhance_resource_lock_audits_table.php'),
        ], 'filament-resource-lock-migrations');

        $this->publishes([
            __DIR__.'/../resources/js/echo.js' => resource_path('js/filament-resource-lock/echo.js'),
        ], 'filament-resource-lock-assets');

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallFilamentResourceLockCommand::class,
            ]);
        }
    }

    private function registerBroadcastChannel(): void
    {
        $prefix = (string) config('filament-resource-lock.transports.broadcast.channel_prefix', 'filament-resource-lock');

        Broadcast::channel($prefix.'.{modelHash}.{id}', fn ($user): bool => (bool) $user);
    }

    private function registerReleaseRoute(): void
    {
        Route::middleware(['web', 'signed'])
            ->get('/filament-resource-lock/release', ReleaseLockController::class)
            ->name('filament-resource-lock.release');
    }
}
