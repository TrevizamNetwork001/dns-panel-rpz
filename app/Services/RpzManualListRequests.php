<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Empresa;
use App\Models\Lista;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RpzManualListRequests
{
    public function __construct(private RpzDirtyMarker $dirty) {}

    public function prepare(int $companyId, array $input): array
    {
        $company = Empresa::query()->findOrFail($companyId);
        $operation = $input['operacao'];
        $list = $operation === 'cadastrar' ? null : $this->list($company, $input['lista_id']);
        $changes = array_intersect_key($input, array_flip(['nome', 'descricao', 'status']));
        if ($operation === 'cadastrar') {
            $changes += ['status' => 'active'];
        }
        abort_if($operation === 'alterar' && $changes === [], 422, 'Informe o que deseja alterar.');

        $id = (string) Str::uuid();
        $expires = now()->addMinutes(10);
        $preview = [
            'tipo' => 'cadastro_lista_manual',
            'solicitacao' => $id,
            'empresa_id' => $company->id,
            'empresa' => $company->nome,
            'operacao' => $operation,
            'lista_id' => $list?->id,
            'lista' => $list?->nome ?? $changes['nome'],
            'estado_atual' => $list === null ? null : $this->snapshot($list),
            'alteracoes' => $changes,
            'motivo' => $input['motivo'],
            'expira_em' => $expires->toISOString(),
        ];
        DB::table('rpz_manual_list_requests')->insert([
            'id' => $id,
            'preview' => json_encode($preview, JSON_THROW_ON_ERROR),
            'expires_at' => $expires,
        ]);

        return $preview;
    }

    public function confirm(string $id, User $operator): array
    {
        return DB::transaction(function () use ($id, $operator): array {
            $query = DB::table('rpz_manual_list_requests')->where('id', $id);
            $claimed = (clone $query)->whereNull('confirmed_at')->where('expires_at', '>', now())
                ->update(['confirmed_at' => now(), 'confirmed_by' => $operator->id]);
            $proposal = $query->first();
            abort_if($proposal === null, 404);
            abort_if($claimed === 0 && (int) $proposal->confirmed_by !== $operator->id,
                409, 'Solicitação expirada ou já processada.');
            $receipt = json_decode($proposal->preview, true, 512, JSON_THROW_ON_ERROR);

            if ($claimed !== 0) {
                $company = Empresa::query()->lockForUpdate()->findOrFail($receipt['empresa_id']);
                abort_if($company->nome !== $receipt['empresa'], 409, 'A empresa mudou desde a prévia.');
                $operation = $receipt['operacao'];
                if ($operation === 'cadastrar') {
                    $list = Lista::create($receipt['alteracoes'] + ['empresa_id' => $company->id, 'origem' => 'manual']);
                } else {
                    $list = $this->list($company, $receipt['lista_id'], lock: true);
                    abort_if($this->snapshot($list) !== $receipt['estado_atual'], 409, 'A lista mudou desde a prévia.');
                    if ($operation === 'remover') {
                        $list->delete();
                    } else {
                        $list->update($receipt['alteracoes']);
                    }
                    $this->dirty->markForServers(array_column($receipt['estado_atual']['servidores'], 'id'));
                }

                $action = match ($operation) {
                    'cadastrar' => 'lista.created',
                    'alterar' => 'lista.updated',
                    'remover' => 'lista.destroyed',
                };
                AuditLog::record($action, "Lista {$list->nome} (Telegram, solicitação {$id}). Motivo: {$receipt['motivo']}",
                    $company->id, 'lista', $list->id);
                $receipt['lista_id'] = $list->id;
                $receipt['lista'] = $list->nome;
                $query->update(['preview' => json_encode($receipt, JSON_THROW_ON_ERROR)]);
            }

            return $receipt + ['confirmada_em' => Carbon::parse($proposal->confirmed_at)->toISOString()];
        });
    }

    private function list(Empresa $company, int $id, bool $lock = false): Lista
    {
        $query = Lista::query()->where('empresa_id', $company->id)->where('origem', 'manual');

        return ($lock ? $query->lockForUpdate() : $query)->findOrFail($id);
    }

    private function snapshot(Lista $list): array
    {
        return $list->only(['nome', 'descricao', 'status']) + [
            'dominios' => $list->dominios()->count(),
            'dominios_atualizados_em' => $list->dominios()->max('updated_at'),
            'servidores' => $list->servidores()->orderBy('servidores.id')->get(['servidores.id', 'servidores.nome'])
                ->map->only(['id', 'nome'])->all(),
        ];
    }
}
