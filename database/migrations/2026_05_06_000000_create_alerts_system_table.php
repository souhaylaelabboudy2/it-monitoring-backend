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
        Schema::create('alert_system', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // unique identifier: server_down_AD, backup_failed_SQL, etc.
            $table->string('title');
            $table->text('message');
            $table->enum('type', ['server', 'backup', 'nvr']); // alert type
            $table->enum('severity', ['critical', 'warning', 'info']); // alert severity
            $table->enum('status', ['active', 'resolved'])->default('active'); // active or resolved
            $table->timestamp('last_seen')->useCurrent(); // last time alert was seen
            $table->timestamps();
            
            // Indexes
            $table->index('key');
            $table->index('status');
            $table->index('severity');
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alert_system');
    }
};
