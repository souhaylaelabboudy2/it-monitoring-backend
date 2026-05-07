<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            // Add severity level for alert escalation tracking
            if (!Schema::hasColumn('alerts', 'severity')) {
                $table->enum('severity', ['info', 'warning', 'critical'])->default('warning')->after('type');
            }
            
            // Add unique key for duplicate prevention and tracking
            if (!Schema::hasColumn('alerts', 'key')) {
                $table->string('key')->unique()->after('severity');
            }
            
            // Add title field for better alert display
            if (!Schema::hasColumn('alerts', 'title')) {
                $table->string('title')->after('key');
            }
            
            // Add status for alert resolution tracking
            if (!Schema::hasColumn('alerts', 'status')) {
                $table->enum('status', ['open', 'resolved', 'acknowledged'])->default('open')->after('title');
            }
        });
    }

    public function down(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            if (Schema::hasColumn('alerts', 'severity')) {
                $table->dropColumn('severity');
            }
            if (Schema::hasColumn('alerts', 'key')) {
                $table->dropColumn('key');
            }
            if (Schema::hasColumn('alerts', 'title')) {
                $table->dropColumn('title');
            }
            if (Schema::hasColumn('alerts', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
