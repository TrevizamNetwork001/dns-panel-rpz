<?php

namespace App\Services;

use App\Models\Lista;
use Illuminate\Support\Facades\DB;

/** Prepare outside the main database, then apply the whole feed atomically. */
class SqliteDomainFeedWriter
{
    public function publish(Lista $lista, array $domains, bool $replace): array
    {
        $stage = 'feed_'.bin2hex(random_bytes(8));
        DB::statement('CREATE TEMP TABLE '.$stage.' (dominio TEXT PRIMARY KEY) WITHOUT ROWID');
        try {
            foreach (array_chunk($domains, 400) as $chunk) {
                DB::table('temp.'.$stage)->insertOrIgnore(array_map(fn ($domain) => ['dominio' => $domain], $chunk));
            }

            $total = count($domains);
            return DB::transaction(function () use ($stage, $lista, $replace, $total): array {
                $added = DB::table('temp.'.$stage.' as feed')->leftJoin('dominios as existing', function ($join) use ($lista) {
                    $join->on('existing.dominio', '=', 'feed.dominio')->where('existing.lista_id', $lista->id);
                })->whereNull('existing.id')->count();
                $reactivated = DB::table('dominios')->where('lista_id', $lista->id)->where('ativo', false)
                    ->when(! $replace, fn ($query) => $query->where(fn ($q) => $q->whereNull('inactive_reason')->orWhere('inactive_reason', '<>', 'anatel_exclusion')))
                    ->whereIn('dominio', DB::table('temp.'.$stage)->select('dominio'))->count();
                $now = now();
                $date = $now->format('Y-m-d H:i:s');
                $updates = 'ativo=1, updated_at=excluded.updated_at'.($replace ? '' : ', inactive_reason=NULL');
                $condition = 'dominios.ativo=0'.($replace ? '' : " AND COALESCE(dominios.inactive_reason, '') <> 'anatel_exclusion'");
                DB::statement('INSERT INTO dominios (lista_id, dominio, ativo, created_at, updated_at) '
                    .'SELECT ?, dominio, 1, ?, ? FROM temp.'.$stage.' WHERE 1 '
                    .'ON CONFLICT (lista_id, dominio) DO UPDATE SET '.$updates.' WHERE '.$condition,
                    [$lista->id, $date, $date]);
                $removed = $replace ? DB::affectingStatement('UPDATE dominios SET ativo=0, updated_at=? '
                    .'WHERE lista_id=? AND ativo=1 AND NOT EXISTS (SELECT 1 FROM temp.'.$stage.' AS feed WHERE feed.dominio=dominios.dominio)',
                    [$date, $lista->id]) : 0;
                $lista->forceFill(['last_sync_at' => $now])->save();
                if ($added + $reactivated + $removed > 0) {
                    app(RpzDirtyMarker::class)->markForList($lista->id);
                }

                return ['status' => 'ok', 'total' => $total, 'adicionados' => $added,
                    'reativados' => $reactivated, 'removidos' => $removed];
            }, 5);
        } finally {
            DB::statement('DROP TABLE IF EXISTS temp.'.$stage);
        }
    }
}
