<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('anatel_imports', function (Blueprint $table) {
   $table->id(); $table->foreignId('lista_id')->constrained('listas')->cascadeOnDelete(); $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
   $table->string('original_filename'); $table->string('storage_path'); $table->char('sha256',64)->unique(); $table->unsignedBigInteger('size_bytes');
   $table->unsignedInteger('pages')->default(0); $table->unsignedInteger('candidates_count')->default(0); $table->unsignedInteger('valid_count')->default(0); $table->unsignedInteger('invalid_count')->default(0);
   $table->unsignedInteger('new_count')->default(0); $table->unsignedInteger('existing_count')->default(0); $table->unsignedInteger('reactivated_count')->default(0); $table->unsignedInteger('excluded_count')->default(0); $table->unsignedInteger('unblocked_count')->default(0);
   $table->string('status')->default('pending'); $table->text('error')->nullable(); $table->timestamp('started_at')->nullable(); $table->timestamp('finished_at')->nullable(); $table->timestamps();
   $table->index(['lista_id','created_at']); $table->index('status');
  });
  Schema::create('anatel_import_domains', function (Blueprint $table) {
   $table->id(); $table->foreignId('anatel_import_id')->constrained('anatel_imports')->cascadeOnDelete(); $table->string('domain'); $table->string('result'); $table->timestamps();
   $table->unique(['anatel_import_id','domain']); $table->index(['anatel_import_id','result']);
  });
 }
 public function down(): void { Schema::dropIfExists('anatel_import_domains'); Schema::dropIfExists('anatel_imports'); }
};
