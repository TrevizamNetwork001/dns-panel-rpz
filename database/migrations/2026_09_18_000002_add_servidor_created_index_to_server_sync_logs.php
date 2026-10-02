<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('server_sync_logs', function (Blueprint $table) {
            $table->index(['servidor_id', 'created_at'], 'server_sync_logs_servidor_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('server_sync_logs', function (Blueprint $table) {
            $table->dropIndex('server_sync_logs_servidor_created_idx');
        });
    }
};
