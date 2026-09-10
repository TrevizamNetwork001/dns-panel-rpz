<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rbl_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rbl_event_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 20);
            $table->string('type', 20);
            $table->string('status', 20);
            $table->string('destination')->nullable();
            $table->string('message_hash', 64)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('error_message')->nullable();
            $table->timestamps();
            $table->unique(['rbl_event_id', 'type', 'channel']);
            $table->index(['created_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rbl_alerts');
    }
};
