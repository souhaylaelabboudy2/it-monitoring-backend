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
        Schema::table('incidents', function (Blueprint $table) {
            if (!Schema::hasColumn('incidents', 'title')) {
                $table->string('title');
            }
            if (!Schema::hasColumn('incidents', 'description')) {
                $table->text('description');
            }
            if (!Schema::hasColumn('incidents', 'severity')) {
                $table->enum('severity', ['low', 'medium', 'high'])->default('medium');
            }
            if (!Schema::hasColumn('incidents', 'status')) {
                $table->enum('status', ['open', 'resolved'])->default('open');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            if (Schema::hasColumn('incidents', 'title')) {
                $table->dropColumn('title');
            }
            if (Schema::hasColumn('incidents', 'description')) {
                $table->dropColumn('description');
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
