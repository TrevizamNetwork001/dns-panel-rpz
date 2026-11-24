<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_api_credentials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('servidor_id')->nullable()->constrained('servidores')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('token_hash', 64)->unique();
            $table->string('scope', 20);
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        Schema::create('tenant_rpz_reports', function (Blueprint $table): void {
            $table->foreignId('servidor_id')->constrained('servidores')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->unsignedBigInteger('serial');
            $table->string('zone_scope', 10);
            $table->timestamp('reported_at');
            $table->primary(['servidor_id', 'empresa_id']);
        });

        Schema::table('listas', function (Blueprint $table): void {
            $table->boolean('tenant_api_write')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('listas', fn (Blueprint $table) => $table->dropColumn('tenant_api_write'));
        Schema::dropIfExists('tenant_rpz_reports');
        Schema::dropIfExists('tenant_api_credentials');
    }
};
