<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listas', function (Blueprint $table) {
            $table->string('origem')->default('manual')->after('status');
            $table->string('fonte_externa')->nullable()->after('origem');
            $table->boolean('sync_ativo')->default(true)->after('fonte_externa');
            $table->timestamp('last_sync_at')->nullable()->after('sync_ativo');
        });
    }

    public function down(): void
    {
        Schema::table('listas', function (Blueprint $table) {
            $table->dropColumn(['origem', 'fonte_externa', 'sync_ativo', 'last_sync_at']);
        });
    }
};
