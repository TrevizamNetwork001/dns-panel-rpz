<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rpz_exception_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('dominio', 253);
            $table->string('motivo', 255);
            $table->timestamp('expires_at')->index();
            $table->timestamp('confirmed_at')->nullable();
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->unsignedBigInteger('exception_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rpz_exception_requests');
    }
};
