<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_allowed_ips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('servidor_id')->constrained('servidores')->cascadeOnDelete();
            $table->string('ip_cidr');
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::table('servidores', function (Blueprint $table) {
            $table->boolean('ip_restriction_enabled')->default(false)->after('token');
        });
    }

    public function down(): void
    {
        Schema::table('servidores', function (Blueprint $table) {
            $table->dropColumn('ip_restriction_enabled');
        });

        Schema::dropIfExists('server_allowed_ips');
    }
};
