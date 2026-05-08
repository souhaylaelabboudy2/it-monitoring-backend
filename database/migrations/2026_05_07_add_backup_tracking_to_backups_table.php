<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backups', function (Blueprint $table) {
            // Add failure tracking fields
            if (!Schema::hasColumn('backups', 'consecutive_failures')) {
                $table->integer('consecutive_failures')->default(0)->after('status');
            }
            
            // Add backup details
            if (!Schema::hasColumn('backups', 'size_gb')) {
                $table->float('size_gb')->nullable()->after('consecutive_failures');
            }
            
            if (!Schema::hasColumn('backups', 'duration_minutes')) {
                $table->integer('duration_minutes')->nullable()->after('size_gb');
            }
            
            if (!Schema::hasColumn('backups', 'error_message')) {
                $table->text('error_message')->nullable()->after('duration_minutes');
            }
            
            // Add indexing for performance
            $table->index('server_name');
            $table->index('status');
            $table->index('consecutive_failures');
        });
    }

    public function down(): void
    {
        Schema::table('backups', function (Blueprint $table) {
            if (Schema::hasColumn('backups', 'consecutive_failures')) {
                $table->dropColumn(['consecutive_failures', 'size_gb', 'duration_minutes', 'error_message']);
            }
            $table->dropIndex(['server_name', 'status', 'consecutive_failures']);
        });
    }
};
