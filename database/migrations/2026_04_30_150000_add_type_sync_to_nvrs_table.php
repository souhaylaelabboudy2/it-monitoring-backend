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
            if (!Schema::hasColumn('nvrs', 'type')) {
                $table->enum('type', ['standard', 'master'])->default('standard');
            }
            if (!Schema::hasColumn('nvrs', 'sync_status')) {
                $table->enum('sync_status', ['synced', 'lost'])->default('synced');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nvrs', function (Blueprint $table) {
            if (Schema::hasColumn('nvrs', 'type')) {
                $table->dropColumn('type');
            }
            if (Schema::hasColumn('nvrs', 'sync_status')) {
                $table->dropColumn('sync_status');
            }
        });
    }
};
