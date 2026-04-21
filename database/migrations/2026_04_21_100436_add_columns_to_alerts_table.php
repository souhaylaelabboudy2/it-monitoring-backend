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
        Schema::table('alerts', function (Blueprint $table) {
            if (!Schema::hasColumn('alerts', 'message')) {
                $table->string('message');
            }
            if (!Schema::hasColumn('alerts', 'type')) {
                $table->string('type')->default('general');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            if (Schema::hasColumn('alerts', 'message')) {
                $table->dropColumn('message');
            }
            if (Schema::hasColumn('alerts', 'type')) {
                $table->dropColumn('type');
            }
        });
    }
};
