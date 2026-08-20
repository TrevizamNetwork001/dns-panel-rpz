<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servidores', function (Blueprint $table) {
            $table->string('bloqueio_modo')->default('nxdomain')->after('tipo_dns');
        });
    }

    public function down(): void
    {
        Schema::table('servidores', function (Blueprint $table) {
            $table->dropColumn('bloqueio_modo');
        });
    }
};
