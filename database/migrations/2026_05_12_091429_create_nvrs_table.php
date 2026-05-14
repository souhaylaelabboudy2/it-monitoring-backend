<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Skip if table already exists (created by 2026_04_19_000000_create_nvrs_table_initial)
        if (Schema::hasTable('nvrs')) {
            return;
        }
        
        Schema::create('nvrs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('status')->default('online');
            $table->integer('cameras_count')->default(0);
            $table->integer('disk_usage')->default(0);
            $table->enum('type', ['standard', 'master'])->default('standard');
            $table->enum('sync_status', ['synced', 'lost'])->default('synced');
            $table->timestamp('last_sync')->nullable();
            $table->timestamp('last_check')->nullable();
            $table->integer('consecutive_sync_losses')->default(0);
            $table->integer('last_offline_duration')->nullable()->comment('Minutes offline');
            $table->timestamps();
            
            // Add indexes for performance
            $table->index('type');
            $table->index('status');
            $table->index('sync_status');
            $table->index('consecutive_sync_losses');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nvrs');
    }
};