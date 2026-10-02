<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historico de versoes de artefato .zone por zona logica (RPZ-VERSION-1).
 * Criada ANTES da coluna rpz_zones.current_version_id (proxima migration)
 * de proposito, pra evitar dependencia circular de schema entre as duas
 * tabelas -- rpz_zones referencia esta tabela, nao o contrario.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rpz_zone_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rpz_zone_id')->constrained('rpz_zones')->cascadeOnDelete();
            $table->unsignedBigInteger('revision');
            $table->unsignedBigInteger('serial');
            $table->string('content_hash', 64);
            $table->string('file_hash', 64)->nullable();
            $table->unsignedBigInteger('domain_count')->default(0);
            $table->unsignedBigInteger('rule_count')->default(0);
            $table->unsignedBigInteger('excluded_count')->default(0);
            // Relativo ao disco 'local' (storage/app/private) -- nunca derivado de
            // token/slug do usuario, so dos IDs internos (rpz_zone_id/version id).
            $table->string('file_path')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('status')->default('building'); // building|validated|published|failed|superseded
            $table->foreignId('previous_version_id')->nullable()->constrained('rpz_zone_versions')->nullOnDelete();
            $table->timestamps();

            $table->unique(['rpz_zone_id', 'revision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rpz_zone_versions');
    }
};
