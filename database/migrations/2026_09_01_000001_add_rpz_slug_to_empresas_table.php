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
            $table->string('rpz_slug', 80)->nullable()->after('nome');
        });

        $used = [];
        foreach (DB::table('empresas')->select(['id', 'nome', 'rpz_slug'])->orderBy('id')->cursor() as $empresa) {
            if ($empresa->rpz_slug !== null && $empresa->rpz_slug !== '') {
                $used[$empresa->rpz_slug] = true;
                continue;
            }

            $base = Str::slug((string) $empresa->nome) ?: 'empresa-'.$empresa->id;
            $base = substr($base, 0, 80);
            $slug = $base;
            $suffix = 2;
            while (isset($used[$slug])) {
                $tail = '-'.$suffix++;
                $slug = substr($base, 0, 80 - strlen($tail)).$tail;
            }

            DB::table('empresas')->where('id', $empresa->id)->update(['rpz_slug' => $slug]);
            $used[$slug] = true;
        }

        Schema::table('empresas', function (Blueprint $table) {
            $table->unique('rpz_slug');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropUnique(['rpz_slug']);
            $table->dropColumn('rpz_slug');
        });
    }
};
