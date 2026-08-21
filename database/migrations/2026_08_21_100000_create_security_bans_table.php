<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_bans', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address', 64);
            $table->string('jail', 32)->default('sshd');
            $table->string('action', 16); // ban | unban
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_bans');
    }
};
