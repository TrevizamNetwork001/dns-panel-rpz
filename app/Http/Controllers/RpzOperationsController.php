<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\Lista;
use App\Services\RpzSynchronizationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RpzOperationsController extends Controller
{
    public function empresas(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'nome' => ['required', 'string', 'max:150'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $name = mb_strtolower(trim($filters['nome']));
        abort_if($name === '', 422, 'Informe o nome da empresa.');
        $literalName = strtr($name, ['!' => '!!', '%' => '!%', '_' => '!_']);
        $pattern = '%'.str_replace(' ', '', $literalName).'%';

        $companies = Empresa::query()
            ->where(function (Builder $query) use ($pattern): void {
                $query->whereRaw("LOWER(REPLACE(nome, ' ', '')) LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("LOWER(REPLACE(nome_fantasia, ' ', '')) LIKE ? ESCAPE '!'", [$pattern]);
            })
            ->orderBy('nome')
            ->orderBy('id')
            ->paginate(15, ['id', 'nome', 'nome_fantasia', 'status'])
            ->withQueryString();

        return response()->json($companies);
    }

    public function vinculos(Request $request, int $empresa): JsonResponse
    {
        $filters = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'servidor_id' => ['sometimes', 'integer', 'min:1'],
        ]);
        $company = Empresa::query()->findOrFail($empresa);
        $serverId = $filters['servidor_id'] ?? null;
        if ($serverId !== null) {
            $company->servidores()->findOrFail($serverId, ['id']);
        }

        $links = DB::table('servidores')
            ->leftJoin('lista_servidor', 'lista_servidor.servidor_id', '=', 'servidores.id')
            ->leftJoin('listas', 'listas.id', '=', 'lista_servidor.lista_id')
            ->where('servidores.empresa_id', $company->id)
            ->when($serverId !== null, fn ($query) => $query->where('servidores.id', $serverId))
            ->orderBy('servidores.id')->orderBy('listas.id')
            ->paginate(25, [
                'servidores.id as servidor_id', 'servidores.nome as servidor_nome', 'servidores.status as servidor_status',
                'listas.id as lista_id', 'listas.nome as lista_nome', 'listas.origem as lista_origem', 'listas.status as lista_status',
            ])
            ->through(fn (object $link): array => [
                'servidor' => ['id' => $link->servidor_id, 'nome' => $link->servidor_nome, 'status' => $link->servidor_status],
                'lista' => $link->lista_id === null ? null : [
                    'id' => $link->lista_id, 'nome' => $link->lista_nome, 'origem' => $link->lista_origem, 'status' => $link->lista_status,
                ],
            ])
            ->withQueryString();

        return response()->json(['empresa' => $company->only(['id', 'nome'])] + $links->toArray());
    }

    public function conteudoLista(Request $request, int $empresa, int $lista): JsonResponse
    {
        $request->validate(['page' => ['sometimes', 'integer', 'min:1']]);
        $company = Empresa::query()->findOrFail($empresa);
        $list = Lista::query()
            ->where('origem', 'manual')
            ->where(fn (Builder $query) => $query->whereNull('empresa_id')->orWhere('empresa_id', $company->id))
            ->withMax('dominios as dominios_atualizados_em', 'updated_at')
            ->findOrFail($lista);
        $domains = $list->dominios()
            ->orderBy('dominio')
            ->orderBy('id')
            ->paginate(25, ['id', 'dominio', 'ativo', 'updated_at'])
            ->withQueryString();

        return response()->json([
            'empresa' => $company->only(['id', 'nome']),
            'lista' => $list->only(['id', 'nome', 'empresa_id', 'origem', 'status', 'updated_at', 'dominios_atualizados_em']),
        ] + $domains->toArray());
    }

    public function sincronizacao(Request $request, int $empresa, RpzSynchronizationStatus $status): JsonResponse
    {
        $request->validate(['page' => ['sometimes', 'integer', 'min:1']]);
        $company = Empresa::query()->findOrFail($empresa);
        $servers = $status->servers($company->id, 15);

        return response()->json([
            'empresa' => $company->only(['id', 'nome', 'nome_fantasia', 'status']),
            'publicacao_empresa' => $status->company($company->id),
            'data' => $servers->items(),
            'current_page' => $servers->currentPage(),
            'last_page' => $servers->lastPage(),
            'per_page' => $servers->perPage(),
            'total' => $servers->total(),
        ]);
    }
}
