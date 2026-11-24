<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rpz_list_link_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->unsignedBigInteger('servidor_id');
            $table->unsignedBigInteger('lista_id');
            $table->string('empresa');
            $table->string('servidor');
            $table->string('lista');
            $table->boolean('vincular');
            $table->unsignedBigInteger('vinculo_id')->nullable();
            $table->string('lista_status');
            $table->unsignedBigInteger('dominios_ativos');
            $table->string('motivo', 255);
            $table->timestamp('expires_at')->index();
            $table->timestamp('confirmed_at')->nullable();
            $table->unsignedBigInteger('confirmed_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rpz_list_link_requests');
    }
};
