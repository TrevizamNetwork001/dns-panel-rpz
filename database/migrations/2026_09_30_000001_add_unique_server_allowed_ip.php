<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('server_allowed_ips')
            ->select('servidor_id', 'ip_cidr')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('servidor_id', 'ip_cidr')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->each(function (object $duplicate): void {
                $ids = DB::table('server_allowed_ips')
                    ->where('servidor_id', $duplicate->servidor_id)
                    ->where('ip_cidr', $duplicate->ip_cidr)
                    ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
                    ->orderBy('id')
                    ->pluck('id')
                    ->all();

                DB::table('server_allowed_ips')->whereIn('id', array_slice($ids, 1))->delete();
            });

        Schema::table('server_allowed_ips', function (Blueprint $table): void {
            $table->unique(['servidor_id', 'ip_cidr'], 'server_allowed_ips_servidor_ip_unique');
        });
    }

    public function down(): void
    {
        Schema::table('server_allowed_ips', function (Blueprint $table): void {
            $table->dropUnique('server_allowed_ips_servidor_ip_unique');
        });
    }
};
