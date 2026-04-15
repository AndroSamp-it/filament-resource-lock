<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('filament-resource-lock.audit.table', 'resource_lock_audits'), function (Blueprint $table): void {
            $table->id();
            $table->string('lockable_type');
            $table->unsignedBigInteger('lockable_id');
            $table->string('event');
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('actor_session_id')->nullable();
            $table->string('actor_display_name')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['lockable_type', 'lockable_id'], 'resource_lock_audits_lockable_index');
            $table->index('event');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('filament-resource-lock.audit.table', 'resource_lock_audits'));
    }
};
