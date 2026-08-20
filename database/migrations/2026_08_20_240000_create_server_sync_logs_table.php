<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('servidor_id')->constrained('servidores')->cascadeOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->unsignedInteger('dominios_count')->default(0);
            $table->timestamp('created_at')->nullable();
        });

        Schema::table('servidores', function (Blueprint $table) {
            $table->string('tipo_dns')->default('unbound')->after('status');
            $table->string('ip_v4', 45)->nullable()->after('tipo_dns');
            $table->string('ip_v6', 45)->nullable()->after('ip_v4');
        });
    }

    public function down(): void
    {
        Schema::table('servidores', function (Blueprint $table) {
            $table->dropColumn(['tipo_dns', 'ip_v4', 'ip_v6']);
        });

        Schema::dropIfExists('server_sync_logs');
    }
};
