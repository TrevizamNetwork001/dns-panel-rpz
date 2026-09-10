<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rbl_events', function (Blueprint $table) {
            $table->string('investigation_status', 20)->nullable()->index();
            $table->text('operator_notes')->nullable();
            $table->timestamp('investigated_at')->nullable();
            $table->foreignId('investigated_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rbl_events', function (Blueprint $table) {
            $table->dropForeign(['investigated_by']);
            $table->dropColumn(['investigation_status', 'operator_notes', 'investigated_at', 'investigated_by']);
        });
    }
};
