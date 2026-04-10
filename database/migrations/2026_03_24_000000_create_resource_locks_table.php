<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('filament-resource-lock.table', 'resource_locks'), function (Blueprint $table): void {
            $table->id();

            // Polymorphic reference to the locked resource.
            $table->string('lockable_type');
            $table->unsignedBigInteger('lockable_id');

            // Who holds the lock.
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('session_id')->nullable();

            // Heartbeat and expiry timestamps.
            $table->timestamp('last_heartbeat_at');
            $table->timestamp('expires_at');
            $table->timestamps();

            // Force-takeover: 0 = none, 1 = save then hand over, 2 = hand over without saving.
            $table->integer('force_takeover')->default(0);
            $table->unsignedBigInteger('force_takeover_user_id')->nullable();
            $table->string('force_takeover_session_id')->nullable();

            // Pending event queue (ask_to_unblock, etc.).
            $table->json('events')->nullable();

            $table->unique(['lockable_type', 'lockable_id'], 'resource_locks_lockable_unique');
            $table->index('expires_at');

            // Two-phase release: lock stays visible during the grace period after page unload.
            $table->boolean('releasing')->default(false)->after('expires_at');
            $table->timestamp('releasing_expires_at')->nullable()->after('releasing');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('filament-resource-lock.table', 'resource_locks'));
    }
};
