<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recursive_dns_servers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->string('name');
            $table->string('hostname');
            $table->string('ipv4', 15)->nullable();
            $table->string('ipv6', 45)->nullable();
            $table->unsignedTinyInteger('ssh_family');
            $table->unsignedSmallInteger('ssh_port');
            $table->string('ssh_user');
            $table->string('ssh_fingerprint');
            $table->text('ssh_password');
            $table->json('diagnostic')->nullable();
            $table->timestamp('diagnosed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recursive_dns_servers');
    }
};
