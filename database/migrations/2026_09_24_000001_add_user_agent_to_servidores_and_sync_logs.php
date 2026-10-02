<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servidores', function (Blueprint $table) {
            $table->string('last_user_agent', 190)->nullable()->after('last_synced_at');
        });

        Schema::table('server_sync_logs', function (Blueprint $table) {
            $table->string('user_agent', 190)->nullable()->after('ip_address');
        });
    }

    public function down(): void
    {
        Schema::table('servidores', function (Blueprint $table) {
            $table->dropColumn('last_user_agent');
        });

        Schema::table('server_sync_logs', function (Blueprint $table) {
            $table->dropColumn('user_agent');
        });
    }
};
