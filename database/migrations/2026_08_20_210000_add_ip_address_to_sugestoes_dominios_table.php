<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sugestoes_dominios', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable()->after('created_by');
        });
    }

    public function down(): void
    {
        Schema::table('sugestoes_dominios', function (Blueprint $table) {
            $table->dropColumn('ip_address');
        });
    }
};
