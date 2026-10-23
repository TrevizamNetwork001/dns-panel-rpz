<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('security_bans', function (Blueprint $table) {
            $table->index(['jail', 'ip_address', 'id'], 'security_bans_latest_state');
            $table->index(['action', 'created_at'], 'security_bans_daily_count');
        });
    }

    public function down(): void
    {
        Schema::table('security_bans', function (Blueprint $table) {
            $table->dropIndex('security_bans_latest_state');
            $table->dropIndex('security_bans_daily_count');
        });
    }
};
