<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->boolean('ip_restriction_enabled')->default(false)->after('api_key');
        });

        Schema::create('empresa_allowed_ips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('ip_cidr', 64);
            $table->string('status')->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresa_allowed_ips');

        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn('ip_restriction_enabled');
        });
    }
};
