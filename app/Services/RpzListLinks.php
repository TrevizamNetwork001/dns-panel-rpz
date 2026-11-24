<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Lista;
use App\Models\Servidor;
use Illuminate\Support\Facades\DB;

class RpzListLinks
{
    public function __construct(private RpzDirtyMarker $dirty) {}

    public function linkId(Servidor $server, Lista $list): ?int
    {
        return DB::table('lista_servidor')->where('servidor_id', $server->id)
            ->where('lista_id', $list->id)->value('id');
    }

    public function update(Servidor $server, Lista $list, bool $attach, ?string $requestId = null, ?string $reason = null): void
    {
        DB::transaction(function () use ($server, $list, $attach, $requestId, $reason): void {
            if ($attach) {
                abort_unless($list->empresa_id === null || $list->empresa_id === $server->empresa_id, 403, 'Esta lista não está disponível para esta empresa.');
                $server->listas()->syncWithoutDetaching([$list->id]);
            } else {
                $server->listas()->detach($list->id);
            }
            $this->dirty->markForServer($server->id);

            if ($requestId !== null) {
                $action = $attach ? 'lista_vinculada' : 'lista_desvinculada';
                AuditLog::record(
                    'servidor.'.$action,
                    "Lista {$list->nome} no servidor {$server->nome} (Telegram, solicitação {$requestId}). Motivo: {$reason}",
                    $server->empresa_id,
                    'servidor',
                    $server->id,
                );
            }
        });
    }
}
