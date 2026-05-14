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

            if (!Schema::hasColumn('nvrs', 'name')) {
                $table->string('name');
            }

            if (!Schema::hasColumn('nvrs', 'type')) {
                $table->string('type')->nullable();
            }

            if (!Schema::hasColumn('nvrs', 'cameras_count')) {
                $table->integer('cameras_count')->default(0);
            }

            if (!Schema::hasColumn('nvrs', 'status')) {
                $table->string('status')->default('online');
            }

            if (!Schema::hasColumn('nvrs', 'disk_usage')) {
                $table->float('disk_usage')->default(0);
            }

            if (!Schema::hasColumn('nvrs', 'last_sync')) {
                $table->timestamp('last_sync')->nullable();
            }

            if (!Schema::hasColumn('nvrs', 'last_check')) {
                $table->timestamp('last_check')->nullable();
            }

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nvrs', function (Blueprint $table) {

            if (Schema::hasColumn('nvrs', 'name')) {
                $table->dropColumn('name');
            }

            if (Schema::hasColumn('nvrs', 'type')) {
                $table->dropColumn('type');
            }

            if (Schema::hasColumn('nvrs', 'cameras_count')) {
                $table->dropColumn('cameras_count');
            }

            if (Schema::hasColumn('nvrs', 'status')) {
                $table->dropColumn('status');
            }

            if (Schema::hasColumn('nvrs', 'disk_usage')) {
                $table->dropColumn('disk_usage');
            }

            if (Schema::hasColumn('nvrs', 'last_sync')) {
                $table->dropColumn('last_sync');
            }

            if (Schema::hasColumn('nvrs', 'last_check')) {
                $table->dropColumn('last_check');
            }

        });
    }
};