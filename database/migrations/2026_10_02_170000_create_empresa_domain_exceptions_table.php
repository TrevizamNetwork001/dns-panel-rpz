<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Reconstructed from the recovered SQLite schema. Existing recovery data stays.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('empresa_domain_exceptions')) {
            return;
        }
        Schema::create('empresa_domain_exceptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('dominio');
            $table->string('motivo')->nullable();
            $table->timestamps();
            $table->unique(['empresa_id', 'dominio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresa_domain_exceptions');
    }
};
