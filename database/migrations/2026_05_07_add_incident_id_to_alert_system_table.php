<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alert_system', function (Blueprint $table) {
            if (!Schema::hasColumn('alert_system', 'incident_id')) {
                $table->unsignedBigInteger('incident_id')->nullable()->after('status');
                $table->index('incident_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('alert_system', function (Blueprint $table) {
            if (Schema::hasColumn('alert_system', 'incident_id')) {
                $table->dropIndex(['incident_id']);
                $table->dropColumn('incident_id');
            }
        });
    }
};
