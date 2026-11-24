<?php

namespace App\Services;

use App\Models\Empresa;
use App\Models\Lista;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RpzListLinkConfirmation
{
    public function __construct(private RpzListLinks $links) {}

    public function confirm(string $id, User $operator): array
    {
        return DB::transaction(function () use ($id, $operator): array {
            $query = DB::table('rpz_list_link_requests')->where('id', $id);
            $claimed = (clone $query)->whereNull('confirmed_at')->where('expires_at', '>', now())
                ->update(['confirmed_at' => now(), 'confirmed_by' => $operator->id]);
            $proposal = $query->first();
            abort_if($proposal === null, 404);
            $attach = (bool) $proposal->vincular;

            if ($claimed === 0) {
                abort_unless((int) $proposal->confirmed_by === $operator->id, 409, 'Solicitação expirada ou já processada.');
            } else {
                $company = Empresa::query()->findOrFail($proposal->empresa_id);
                $server = $company->servidores()->lockForUpdate()->findOrFail($proposal->servidor_id);
                $list = Lista::query()->lockForUpdate()->findOrFail($proposal->lista_id);
                abort_if(
                    $company->nome !== $proposal->empresa || $server->nome !== $proposal->servidor || $list->nome !== $proposal->lista
                    || $this->links->linkId($server, $list) !== ($proposal->vinculo_id === null ? null : (int) $proposal->vinculo_id)
                    || $list->status !== $proposal->lista_status
                    || $list->dominios()->where('ativo', true)->count() !== (int) $proposal->dominios_ativos,
                    409,
                    'O vínculo mudou desde a prévia. Prepare uma nova solicitação.',
                );
                $this->links->update($server, $list, $attach, $id, $proposal->motivo);
            }

            return [
                'solicitacao' => $id,
                'operacao' => $attach ? 'vincular' : 'desvincular',
                'empresa_id' => $proposal->empresa_id,
                'empresa' => $proposal->empresa,
                'servidor' => $proposal->servidor,
                'lista' => $proposal->lista,
                'confirmada_em' => Carbon::parse($proposal->confirmed_at)->toISOString(),
            ];
        });
    }
}
