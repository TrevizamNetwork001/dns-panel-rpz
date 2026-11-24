<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\Lista;
use App\Services\RpzListLinks;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RpzListLinkRequestController extends Controller
{
    public function listas(Request $request, int $empresa): JsonResponse
    {
        $filters = $request->validate([
            'nome' => ['required', 'string', 'max:150'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $company = Empresa::query()->findOrFail($empresa);
        $pattern = '%'.strtr(mb_strtolower(trim($filters['nome'])), ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
        $lists = Lista::query()
            ->where(fn (Builder $query) => $query->whereNull('empresa_id')->orWhere('empresa_id', $company->id))
            ->whereRaw("LOWER(nome) LIKE ? ESCAPE '!'", [$pattern])
            ->orderBy('nome')->orderBy('id')
            ->paginate(15, ['id', 'nome', 'origem', 'status', 'empresa_id'])->withQueryString();

        return response()->json(['empresa' => $company->only(['id', 'nome'])] + $lists->toArray());
    }

    public function prepare(Request $request, int $empresa, RpzListLinks $links): JsonResponse
    {
        $data = $request->validate([
            'servidor_id' => ['required', 'integer', 'min:1'],
            'lista_id' => ['required', 'integer', 'min:1'],
            'operacao' => ['required', 'in:vincular,desvincular'],
            'motivo' => ['required', 'string', 'max:255'],
        ]);
        $company = Empresa::query()->findOrFail($empresa);
        $server = $company->servidores()->findOrFail($data['servidor_id']);
        $list = Lista::query()->findOrFail($data['lista_id']);
        $attach = $data['operacao'] === 'vincular';
        abort_if($attach && $list->empresa_id !== null && $list->empresa_id !== $company->id, 403, 'Lista de outra empresa.');
        $linkId = $links->linkId($server, $list);
        abort_if(($linkId !== null) === $attach, 409, 'O vínculo já está no estado solicitado.');
        $domainCount = $list->dominios()->where('ativo', true)->count();

        $id = (string) Str::uuid();
        $expires = now()->addMinutes(10);
        DB::table('rpz_list_link_requests')->insert([
            'id' => $id,
            'empresa_id' => $company->id,
            'servidor_id' => $server->id,
            'lista_id' => $list->id,
            'empresa' => $company->nome,
            'servidor' => $server->nome,
            'lista' => $list->nome,
            'vincular' => $attach,
            'vinculo_id' => $linkId,
            'lista_status' => $list->status,
            'dominios_ativos' => $domainCount,
            'motivo' => $data['motivo'],
            'expires_at' => $expires,
        ]);

        return response()->json(['data' => [
            'solicitacao' => $id,
            'operacao' => $data['operacao'],
            'empresa' => $company->nome,
            'servidor' => $server->nome,
            'lista' => $list->nome,
            'lista_status' => $list->status,
            'dominios_ativos' => $domainCount,
            'motivo' => $data['motivo'],
            'expira_em' => $expires->toISOString(),
        ]], 201);
    }
}
