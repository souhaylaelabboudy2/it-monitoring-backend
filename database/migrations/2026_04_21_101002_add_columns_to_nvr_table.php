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
            if (!Schema::hasColumn('nvr', 'name')) {
                $table->string('name');
            }
            if (!Schema::hasColumn('nvr', 'type')) {
                $table->string('type')->nullable();
            }
            if (!Schema::hasColumn('nvr', 'cameras_count')) {
                $table->integer('cameras_count')->default(0);
            }
            if (!Schema::hasColumn('nvr', 'status')) {
                $table->string('status')->default('online');
            }
            if (!Schema::hasColumn('nvr', 'disk_usage')) {
                $table->float('disk_usage')->default(0);
            }
            if (!Schema::hasColumn('nvr', 'last_sync')) {
                $table->timestamp('last_sync')->nullable();
            }
            if (!Schema::hasColumn('nvr', 'last_check')) {
                $table->timestamp('last_check')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nvr', function (Blueprint $table) {
            if (Schema::hasColumn('nvr', 'name')) {
                $table->dropColumn('name');
            }
            if (Schema::hasColumn('nvr', 'type')) {
                $table->dropColumn('type');
            }
            if (Schema::hasColumn('nvr', 'cameras_count')) {
                $table->dropColumn('cameras_count');
            }
            if (Schema::hasColumn('nvr', 'status')) {
                $table->dropColumn('status');
            }
            if (Schema::hasColumn('nvr', 'disk_usage')) {
                $table->dropColumn('disk_usage');
            }
            if (Schema::hasColumn('nvr', 'last_sync')) {
                $table->dropColumn('last_sync');
            }
            if (Schema::hasColumn('nvr', 'last_check')) {
                $table->dropColumn('last_check');
            }
        });
    }
};
