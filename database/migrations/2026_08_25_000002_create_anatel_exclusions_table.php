<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('anatel_exclusions', function (Blueprint $table) {
   $table->id(); $table->foreignId('lista_id')->constrained('listas')->cascadeOnDelete(); $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
   $table->string('type'); $table->text('value'); $table->char('value_hash',64); $table->boolean('active')->default(true); $table->text('notes')->nullable(); $table->timestamps();
   $table->unique(['lista_id','type','value_hash']); $table->index(['lista_id','active']);
  });
  Schema::table('dominios', function (Blueprint $table) {
   $table->string('inactive_reason')->nullable()->after('ativo'); $table->foreignId('last_anatel_import_id')->nullable()->after('inactive_reason')->constrained('anatel_imports')->nullOnDelete(); $table->index(['lista_id','ativo']);
  });
 }
 public function down(): void {
  Schema::table('dominios', function (Blueprint $table) { $table->dropForeign(['last_anatel_import_id']); $table->dropIndex(['lista_id','ativo']); $table->dropColumn(['inactive_reason','last_anatel_import_id']); });
  Schema::dropIfExists('anatel_exclusions');
 }
};
