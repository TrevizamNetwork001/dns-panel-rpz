<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rbl_checks', function (Blueprint $table) {
            $table->index(['rbl_list_id', 'checked_at']);
            $table->index(['rbl_target_id', 'rbl_list_id', 'checked_value', 'checked_at'], 'rbl_checks_event_history_index');
        });
        Schema::table('rbl_events', function (Blueprint $table) {
            $table->index(['status', 'last_seen_at']);
            $table->index(['rbl_target_id', 'rbl_list_id', 'last_checked_value', 'status'], 'rbl_events_open_lookup_index');
            $table->index(['rbl_list_id', 'last_checked_value', 'first_seen_at'], 'rbl_events_value_recurrence_index');
        });
        Schema::table('rbl_runs', function (Blueprint $table) {
            $table->index('started_at');
        });
        Schema::table('rbl_targets', function (Blueprint $table) {
            $table->index(['enabled', 'last_status']);
            $table->index(['rbl_target_group_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::table('rbl_targets', function (Blueprint $table) {
            $table->dropIndex(['enabled', 'last_status']);
            $table->dropIndex(['rbl_target_group_id', 'type']);
        });
        Schema::table('rbl_runs', function (Blueprint $table) {
            $table->dropIndex(['started_at']);
        });
        Schema::table('rbl_events', function (Blueprint $table) {
            $table->dropIndex(['status', 'last_seen_at']);
            $table->dropIndex('rbl_events_open_lookup_index');
            $table->dropIndex('rbl_events_value_recurrence_index');
        });
        Schema::table('rbl_checks', function (Blueprint $table) {
            $table->dropIndex(['rbl_list_id', 'checked_at']);
            $table->dropIndex('rbl_checks_event_history_index');
        });
    }
};
