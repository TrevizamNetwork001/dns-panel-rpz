<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('empresas', 'rpz_bloqueio_modo')) {
            Schema::table('empresas', fn (Blueprint $table) => $table->string('rpz_bloqueio_modo')->default('nxdomain'));
        }
    }

    public function down(): void
    {
        Schema::table('empresas', fn (Blueprint $table) => $table->dropColumn('rpz_bloqueio_modo'));
    }
};
