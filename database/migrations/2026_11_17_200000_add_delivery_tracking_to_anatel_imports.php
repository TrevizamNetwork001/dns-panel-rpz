<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anatel_imports', function (Blueprint $table): void {
            $table->boolean('from_mailbox')->default(false);
            $table->foreignId('reporting_empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->timestamp('telegram_notified_at')->nullable();
            $table->timestamp('email_notified_at')->nullable();
            $table->timestamp('email_skipped_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('anatel_imports', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reporting_empresa_id');
            $table->dropColumn(['from_mailbox', 'telegram_notified_at', 'email_notified_at', 'email_skipped_at']);
        });
    }
};
