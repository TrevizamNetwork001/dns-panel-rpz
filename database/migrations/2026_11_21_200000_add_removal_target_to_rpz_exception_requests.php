<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rpz_exception_requests', function (Blueprint $table): void {
            $table->unsignedBigInteger('remove_exception_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('rpz_exception_requests', function (Blueprint $table): void {
            $table->dropColumn('remove_exception_id');
        });
    }
};
