<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Reconstructed from the recovered SQLite schema. Existing recovery data stays.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lista_domain_exceptions')) {
            return;
        }
        Schema::create('lista_domain_exceptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lista_id')->constrained('listas')->cascadeOnDelete();
            $table->string('dominio');
            $table->string('motivo')->nullable();
            $table->timestamps();
            $table->unique(['lista_id', 'dominio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lista_domain_exceptions');
    }
};
