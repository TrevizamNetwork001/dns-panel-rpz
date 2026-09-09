<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rbl_targets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 20);
            $table->string('value');
            $table->string('category', 20)->nullable();
            $table->text('description')->nullable();
            $table->boolean('enabled')->default(true);
            $table->string('last_status', 20)->nullable()->default('unchecked');
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
        });
        Schema::create('rbl_lists', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('dns_zone')->unique();
            $table->string('type', 20);
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('timeout_seconds')->default(5);
            $table->text('description')->nullable();
            $table->timestamps();
        });
        Schema::create('rbl_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rbl_target_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rbl_list_id')->constrained()->cascadeOnDelete();
            $table->string('checked_value');
            $table->string('query')->nullable();
            $table->string('status', 20);
            $table->text('response')->nullable();
            $table->text('response_text')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('checked_at');
            $table->timestamps();
            $table->index(['rbl_target_id', 'checked_at']);
        });
        Schema::create('rbl_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rbl_target_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rbl_list_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20);
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('resolved_at')->nullable();
            $table->text('last_response')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['rbl_target_id', 'rbl_list_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rbl_events');
        Schema::dropIfExists('rbl_checks');
        Schema::dropIfExists('rbl_lists');
        Schema::dropIfExists('rbl_targets');
    }
};
