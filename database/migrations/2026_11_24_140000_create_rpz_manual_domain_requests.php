<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rpz_manual_domain_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->text('preview');
            $table->unsignedBigInteger('domain_id')->nullable();
            $table->string('domain_updated_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->unsignedBigInteger('confirmed_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rpz_manual_domain_requests');
    }
};
