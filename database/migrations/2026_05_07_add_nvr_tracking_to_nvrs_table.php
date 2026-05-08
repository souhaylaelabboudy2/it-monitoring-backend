<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('nvr', function (Blueprint $table) {
            // Add escalation tracking
            if (!Schema::hasColumn('nvr', 'consecutive_sync_losses')) {
                $table->integer('consecutive_sync_losses')->default(0);
            }
            
            // Add detail tracking
            if (!Schema::hasColumn('nvr', 'last_offline_duration')) {
                $table->integer('last_offline_duration')->nullable()->comment('Minutes offline');
            }
            
            // Add indexes for performance
            if (!Schema::hasIndexes('nvr', 'idx_type_sync')) {
                $table->index('type');
            }
            if (!Schema::hasIndexes('nvr', 'idx_status')) {
                $table->index('status');
            }
            if (!Schema::hasIndexes('nvr', 'idx_sync_status')) {
                $table->index('sync_status');
            }
            if (!Schema::hasIndexes('nvr', 'idx_consecutive_sync_losses')) {
                $table->index('consecutive_sync_losses');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nvr', function (Blueprint $table) {
            if (Schema::hasColumn('nvr', 'consecutive_sync_losses')) {
                $table->dropColumn('consecutive_sync_losses');
            }
            if (Schema::hasColumn('nvr', 'last_offline_duration')) {
                $table->dropColumn('last_offline_duration');
            }
            if (Schema::hasIndex('nvr', 'idx_type_sync')) {
                $table->dropIndex('idx_type_sync');
            }
            if (Schema::hasIndex('nvr', 'idx_status')) {
                $table->dropIndex('idx_status');
            }
            if (Schema::hasIndex('nvr', 'idx_sync_status')) {
                $table->dropIndex('idx_sync_status');
            }
            if (Schema::hasIndex('nvr', 'idx_consecutive_sync_losses')) {
                $table->dropIndex('idx_consecutive_sync_losses');
            }
        });
    }
};
