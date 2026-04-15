<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $auditTable = config('filament-resource-lock.audit.table', 'resource_lock_audits');
        $lockTable = config('filament-resource-lock.table', 'resource_locks');

        Schema::table($auditTable, function (Blueprint $table) {
            $table->string('lock_cycle_id', 36)->nullable()->after('lockable_id')->index();
            $table->unsignedInteger('version')->nullable()->after('lock_cycle_id');
            $table->json('snapshot')->nullable()->after('payload');
            $table->json('changes')->nullable()->after('snapshot');

            $table->index('version');
        });

        Schema::table($lockTable, function (Blueprint $table) {
            $table->string('lock_cycle_id', 36)->nullable()->after('session_id')->index();
        });
    }

    public function down(): void
    {
        $auditTable = config('filament-resource-lock.audit.table', 'resource_lock_audits');
        $lockTable = config('filament-resource-lock.table', 'resource_locks');

        Schema::table($auditTable, function (Blueprint $table) {
            $table->dropIndex('resource_lock_audits_lock_cycle_id_index');
            $table->dropIndex('resource_lock_audits_version_index');
            $table->dropColumn(['lock_cycle_id', 'version', 'snapshot', 'changes']);
        });

        Schema::table($lockTable, function (Blueprint $table) {
            $table->dropIndex('resource_locks_lock_cycle_id_index');
            $table->dropColumn('lock_cycle_id');
        });
    }
};
