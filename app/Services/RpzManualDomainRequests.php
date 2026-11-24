<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Empresa;
use App\Models\Lista;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RpzManualDomainRequests
{
    public function __construct(private DomainNormalizer $normalizer, private RpzDirtyMarker $dirty) {}

    public function prepare(int $empresa, int $lista, array $input): array
    {
        $domain = $this->normalizer->normalize($input['dominio'], false);
        abort_if($domain === null, 422, 'Domínio inválido.');
        $company = Empresa::query()->findOrFail($empresa);
        $list = $this->manualList($company, $lista);
        $rule = $list->dominios()->where('dominio', $domain)->first();
        $add = $input['operacao'] === 'incluir';
        abort_if($add ? $rule !== null : $rule === null, 409, $add
            ? 'O domínio já está cadastrado na lista.'
            : 'O domínio não está cadastrado na lista.');

        $id = (string) Str::uuid();
        $expires = now()->addMinutes(10);
        $preview = [
            'solicitacao' => $id,
            'operacao' => $input['operacao'],
            'empresa_id' => $company->id,
            'empresa' => $company->nome,
            'lista_id' => $list->id,
            'lista' => $list->nome,
            'lista_status' => $list->status,
            'dominio' => $domain,
            'regra_ativa' => $rule?->ativo ?? true,
            'servidores_vinculados' => $this->servers($list),
            'motivo' => $input['motivo'],
            'expira_em' => $expires->toISOString(),
        ];
        DB::table('rpz_manual_domain_requests')->insert([
            'id' => $id,
            'preview' => json_encode($preview, JSON_THROW_ON_ERROR),
            'domain_id' => $rule?->id,
            'domain_updated_at' => $rule?->updated_at?->toISOString(),
            'expires_at' => $expires,
        ]);

        return $preview;
    }

    public function confirm(string $id, User $operator): array
    {
        return DB::transaction(function () use ($id, $operator): array {
            $query = DB::table('rpz_manual_domain_requests')->where('id', $id);
            $claimed = (clone $query)->whereNull('confirmed_at')->where('expires_at', '>', now())
                ->update(['confirmed_at' => now(), 'confirmed_by' => $operator->id]);
            $proposal = $query->first();
            abort_if($proposal === null, 404);
            abort_if($claimed === 0 && (int) $proposal->confirmed_by !== $operator->id,
                409, 'Solicitação expirada ou já processada.');

            $preview = json_decode($proposal->preview, true, 512, JSON_THROW_ON_ERROR);
            if ($claimed !== 0) {
                $company = Empresa::query()->findOrFail($preview['empresa_id']);
                $list = $this->manualList($company, $preview['lista_id'], lock: true);
                $rule = $list->dominios()->where('dominio', $preview['dominio'])->lockForUpdate()->first();
                abort_if(
                    $company->nome !== $preview['empresa'] || $list->nome !== $preview['lista']
                    || $list->status !== $preview['lista_status'] || $this->servers($list) !== $preview['servidores_vinculados']
                    || $rule?->id !== ($proposal->domain_id === null ? null : (int) $proposal->domain_id)
                    || $rule?->updated_at?->toISOString() !== $proposal->domain_updated_at
                    || ($rule !== null && $rule->ativo !== $preview['regra_ativa']),
                    409, 'A lista, os vínculos ou a regra mudaram desde a prévia. Gere uma nova solicitação.',
                );
                if ($preview['operacao'] === 'incluir') {
                    $list->dominios()->create(['dominio' => $preview['dominio'], 'ativo' => true]);
                } else {
                    $rule->delete();
                }
                $this->dirty->markForList($list->id);
                AuditLog::record(
                    'lista.dominio_'.$preview['operacao'],
                    "Domínio {$preview['dominio']} na lista {$list->nome} (Telegram, solicitação {$id}). Motivo: {$preview['motivo']}",
                    $company->id,
                    'lista',
                    $list->id,
                );
            }

            return $preview + ['confirmada_em' => Carbon::parse($proposal->confirmed_at)->toISOString()];
        });
    }

    private function manualList(Empresa $company, int $id, bool $lock = false): Lista
    {
        $query = Lista::query()->where('origem', 'manual')->where('empresa_id', $company->id);

        return ($lock ? $query->lockForUpdate() : $query)->findOrFail($id);
    }

    private function servers(Lista $list): array
    {
        return $list->servidores()->with('empresa:id,nome')->orderBy('servidores.id')->get()
            ->map(fn ($server) => [
                'id' => $server->id,
                'nome' => $server->nome,
                'status' => $server->status,
                'empresa_id' => $server->empresa_id,
                'empresa' => $server->empresa?->nome,
            ])->all();
    }
}
