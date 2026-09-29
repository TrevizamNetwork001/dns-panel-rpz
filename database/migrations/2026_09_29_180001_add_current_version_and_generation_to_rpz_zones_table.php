<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RPZ-BUILD-1: liga cada zona logica a sua versao de artefato publicada
 * atual, e adiciona o par desired_generation/built_generation usado como
 * "dirty marker" durável (fonte de verdade pra saber se a zona precisa de
 * rebuild -- nao depende de fila ser unica nem de timestamp).
 *
 * IMPORTANTE: a FK de current_version_id sozinha NAO garante que aquela
 * versao pertence a ESTA rpz_zone (poderia apontar pra versao de outra
 * zona, tecnicamente). Essa invariante e responsabilidade do
 * RpzZoneBuildService (validada e testada), o banco nao impoe isso aqui.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rpz_zones', function (Blueprint $table) {
            $table->foreignId('current_version_id')->nullable()->after('id')->constrained('rpz_zone_versions')->nullOnDelete();
            $table->unsignedBigInteger('desired_generation')->default(0)->after('excluded_count');
            $table->unsignedBigInteger('built_generation')->default(0)->after('desired_generation');
        });
    }

    public function down(): void
    {
        Schema::table('rpz_zones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_version_id');
            $table->dropColumn(['desired_generation', 'built_generation']);
        });
    }
};
