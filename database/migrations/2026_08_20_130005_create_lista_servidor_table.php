<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lista_servidor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lista_id')->constrained('listas')->cascadeOnDelete();
            $table->foreignId('servidor_id')->constrained('servidores')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['lista_id', 'servidor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lista_servidor');
    }
};
