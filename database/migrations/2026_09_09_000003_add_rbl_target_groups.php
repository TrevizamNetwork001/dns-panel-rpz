<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rbl_target_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('category', 20)->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });
        Schema::table('rbl_targets', function (Blueprint $table) {
            $table->foreignId('rbl_target_group_id')->nullable()->constrained()->nullOnDelete();
        });
        Schema::table('rbl_events', function (Blueprint $table) {
            $table->string('last_checked_value')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('rbl_events', function (Blueprint $table) {
            $table->dropColumn('last_checked_value');
        });
        Schema::table('rbl_targets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rbl_target_group_id');
        });
        Schema::dropIfExists('rbl_target_groups');
    }
};
