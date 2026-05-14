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
        Schema::table('nvrs', function (Blueprint $table) {
            // Add escalation tracking
            if (!Schema::hasColumn('nvrs', 'consecutive_sync_losses')) {
                $table->integer('consecutive_sync_losses')->default(0);
            }
            
            // Add detail tracking
            if (!Schema::hasColumn('nvrs', 'last_offline_duration')) {
                $table->integer('last_offline_duration')->nullable()->comment('Minutes offline');
            }
            
            // Indexes are already created in the initial create migration
            // No need to add them again
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nvrs', function (Blueprint $table) {
            if (Schema::hasColumn('nvrs', 'consecutive_sync_losses')) {
                $table->dropColumn('consecutive_sync_losses');
            }
            if (Schema::hasColumn('nvrs', 'last_offline_duration')) {
                $table->dropColumn('last_offline_duration');
            }
            if (Schema::hasIndex('nvrs', 'idx_type_sync')) {
                $table->dropIndex('idx_type_sync');
            }
            if (Schema::hasIndex('nvrs', 'idx_status')) {
                $table->dropIndex('idx_status');
            }
            if (Schema::hasIndex('nvrs', 'idx_sync_status')) {
                $table->dropIndex('idx_sync_status');
            }
            if (Schema::hasIndex('nvrs', 'idx_consecutive_sync_losses')) {
                $table->dropIndex('idx_consecutive_sync_losses');
            }
        });
    }
};
