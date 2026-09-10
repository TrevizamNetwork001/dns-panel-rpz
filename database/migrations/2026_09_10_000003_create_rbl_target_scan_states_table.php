<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rbl_target_scan_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rbl_target_id')->unique()->constrained('rbl_targets')->cascadeOnDelete();
            $table->unsignedInteger('cursor')->default(0);
            $table->unsignedInteger('cycle')->default(0);
            $table->unsignedInteger('total_ips')->default(0);
            $table->unsignedInteger('scanned_ips')->default(0);
            $table->unsignedInteger('listed_ips')->default(0);
            $table->unsignedInteger('clean_ips')->default(0);
            $table->unsignedInteger('skipped_ips')->default(0);
            $table->unsignedInteger('error_ips')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->text('summary')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rbl_target_scan_states');
    }
};
