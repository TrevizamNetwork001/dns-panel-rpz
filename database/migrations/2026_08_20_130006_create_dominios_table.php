<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dominios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lista_id')->constrained('listas')->cascadeOnDelete();
            $table->string('dominio');
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->unique(['lista_id', 'dominio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dominios');
    }
};
