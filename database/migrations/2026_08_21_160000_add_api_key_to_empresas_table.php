<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('api_key', 64)->nullable()->unique()->after('status');
        });

        // Backfill: empresas ja existentes ganham uma chave (novas ja ganham via Empresa::booted()).
        foreach (DB::table('empresas')->whereNull('api_key')->pluck('id') as $id) {
            DB::table('empresas')->where('id', $id)->update(['api_key' => Str::random(48)]);
        }
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn('api_key');
        });
    }
};
