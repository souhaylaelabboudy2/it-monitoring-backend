<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            // Add unique key for tracking and deduplication
            if (!Schema::hasColumn('incidents', 'key')) {
                $table->string('key')->unique()->nullable()->after('id');
                $table->index('key');
            }

            // Add severity enum for consistency
            if (Schema::hasColumn('incidents', 'severity')) {
                // Rename existing severity column to match enum
                $table->dropColumn('severity');
            }
            $table->enum('severity', ['info', 'warning', 'critical'])->default('warning')->after('title');

            // Ensure status column exists with proper values
            if (Schema::hasColumn('incidents', 'status')) {
                $table->dropColumn('status');
            }
            $table->enum('status', ['open', 'acknowledged', 'resolved'])->default('open')->after('severity');
        });
    }

    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            if (Schema::hasColumn('incidents', 'key')) {
                $table->dropUnique(['key']);
                $table->dropIndex(['key']);
                $table->dropColumn('key');
            }
            if (Schema::hasColumn('incidents', 'severity')) {
                $table->dropColumn('severity');
            }
            if (Schema::hasColumn('incidents', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
