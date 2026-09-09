<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rbl_runs', function (Blueprint $table) {
            $table->id();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->string('status', 20)->index();
            foreach (['targets_checked', 'checks_created', 'listed_count', 'clean_count', 'skipped_count', 'error_count'] as $column) {
                $table->unsignedInteger($column)->default(0);
            }
            $table->unsignedBigInteger('duration_ms')->nullable();
            $table->string('error_message')->nullable();
            $table->timestamps();
        });
        Schema::table('rbl_checks', function (Blueprint $table) {
            $table->foreignId('rbl_run_id')->nullable()->constrained()->nullOnDelete();
            $table->index(['status', 'checked_at']);
        });
        Schema::table('rbl_events', function (Blueprint $table) {
            $table->index('first_seen_at');
            $table->index(['status', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::table('rbl_events', function (Blueprint $table) {
            $table->dropIndex(['first_seen_at']);
            $table->dropIndex(['status', 'resolved_at']);
        });
        Schema::table('rbl_checks', function (Blueprint $table) {
            $table->dropIndex(['status', 'checked_at']);
            $table->dropConstrainedForeignId('rbl_run_id');
        });
        Schema::dropIfExists('rbl_runs');
    }
};
