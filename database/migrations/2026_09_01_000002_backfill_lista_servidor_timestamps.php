<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Older relations did not use withTimestamps(), leaving the pivot date
        // null. Use the server creation date as the safest available lower bound
        // so its activity log can include changes made after the server existed.
        DB::table('lista_servidor')
            ->whereNull('created_at')
            ->update([
                'created_at' => DB::raw('COALESCE((SELECT servidores.created_at FROM servidores WHERE servidores.id = lista_servidor.servidor_id), CURRENT_TIMESTAMP)'),
            ]);

        DB::table('lista_servidor')
            ->whereNull('updated_at')
            ->update(['updated_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        // The original schema permits null timestamps; restoring them would
        // discard useful history, so this data repair is intentionally retained.
    }
};
