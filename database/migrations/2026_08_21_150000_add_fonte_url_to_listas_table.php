<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listas', function (Blueprint $table) {
            $table->string('fonte_url')->nullable()->after('fonte_externa');
            $table->string('fonte_formato')->default('hostfile')->after('fonte_url');
        });

        // Dado existente: a lista do URLhaus (unica fonte externa ate aqui) ganha a URL real.
        DB::table('listas')
            ->where('fonte_externa', 'urlhaus')
            ->update([
                'fonte_url' => 'https://urlhaus.abuse.ch/downloads/hostfile/',
                'fonte_formato' => 'hostfile',
            ]);
    }

    public function down(): void
    {
        Schema::table('listas', function (Blueprint $table) {
            $table->dropColumn(['fonte_url', 'fonte_formato']);
        });
    }
};
