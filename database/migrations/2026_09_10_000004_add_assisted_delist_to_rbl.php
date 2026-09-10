<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rbl_lists', function (Blueprint $table) {
            $table->string('lookup_url', 2048)->nullable();
            $table->string('delist_url', 2048)->nullable();
            $table->text('delist_instructions')->nullable();
            $table->boolean('delist_requires_manual_review')->default(true);
        });

        Schema::create('rbl_delist_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rbl_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 30);
            $table->timestamp('requested_at')->nullable();
            $table->string('request_url', 2048)->nullable();
            $table->string('protocol')->nullable();
            $table->string('contact_email')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['rbl_event_id', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rbl_delist_requests');

        Schema::table('rbl_lists', function (Blueprint $table) {
            $table->dropColumn(['lookup_url', 'delist_url', 'delist_instructions', 'delist_requires_manual_review']);
        });
    }
};
