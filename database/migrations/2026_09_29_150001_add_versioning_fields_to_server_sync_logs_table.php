<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RPZ-OBS-1 (auditoria 29/09/2026, secao 9-11): o registro de sincronizacao
 * gravava so a contagem SQL antes mesmo de gerar a resposta, sem distinguir
 * "requisicao recebida" de "resposta gerada" e sem identificador por
 * requisicao. Campos novos sao nullable porque linhas antigas nao tem essa
 * informacao (geradas antes do versionamento persistido existir).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('server_sync_logs', function (Blueprint $table) {
            $table->uuid('request_id')->nullable()->after('id');
            $table->string('zone_key')->nullable()->after('servidor_id');
            $table->unsignedBigInteger('revision')->nullable()->after('dominios_count');
            $table->string('content_hash', 64)->nullable()->after('revision');
            $table->unsignedBigInteger('rule_count')->nullable()->after('content_hash');
            $table->unsignedBigInteger('excluded_count')->nullable()->after('rule_count');
            $table->unsignedInteger('duration_ms')->nullable()->after('excluded_count');
        });
    }

    public function down(): void
    {
        Schema::table('server_sync_logs', function (Blueprint $table) {
            $table->dropColumn(['request_id', 'zone_key', 'revision', 'content_hash', 'rule_count', 'excluded_count', 'duration_ms']);
        });
    }
};
