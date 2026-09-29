<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persiste o estado de versao de cada zona logica (RPZ-SERIAL-1, auditoria
 * 29/09/2026). Antes disso o serial era recalculado a cada requisicao via
 * CRC32b do conteudo (RpzZoneBuilder::computeSerial()), sem historico e sem
 * garantia de nao regredir. zone_key identifica a zona de forma inequivoca
 * -- "server:<id>" ou "company:<id>" -- porque o endpoint por token e o
 * endpoint empresarial por slug tem conteudos diferentes mesmo usando o
 * mesmo nome textual de zona (rpz.trevizamnetwork.com.br) e NAO podem
 * compartilhar serial.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rpz_zones', function (Blueprint $table) {
            $table->id();
            $table->string('zone_key')->unique();
            $table->string('scope_type');
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->foreignId('servidor_id')->nullable()->constrained('servidores')->nullOnDelete();
            $table->string('zone_name');
            // Cabe em unsignedInteger (max 4294967295), mas unsignedBigInteger
            // evita qualquer risco de overflow silencioso em bancos diferentes.
            $table->unsignedBigInteger('last_serial');
            $table->unsignedBigInteger('revision');
            $table->string('content_hash', 64);
            $table->unsignedBigInteger('domain_count')->default(0);
            $table->unsignedBigInteger('rule_count')->default(0);
            $table->unsignedBigInteger('excluded_count')->default(0);
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rpz_zones');
    }
};
