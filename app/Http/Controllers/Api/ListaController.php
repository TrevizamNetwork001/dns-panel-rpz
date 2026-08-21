<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\HasApiActor;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Lista;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ListaController extends Controller
{
    use HasApiActor;

    public function index(Request $request): JsonResponse
    {
        $query = Lista::query();

        if (! $this->isAdmin($request)) {
            $query->where(function ($q) use ($request) {
                $q->whereNull('empresa_id')->orWhere('empresa_id', $this->empresaId($request));
            });
        }

        $listas = $query->orderByDesc('id')->paginate(min((int) $request->input('per_page', 20), 100));

        return response()->json([
            'data' => $listas->getCollection()->map(fn (Lista $l) => $this->transform($l)),
            'meta' => [
                'current_page' => $listas->currentPage(),
                'last_page' => $listas->lastPage(),
                'total' => $listas->total(),
            ],
        ]);
    }

    public function show(Request $request, Lista $lista): JsonResponse
    {
        $this->authorizeAccess($request, $lista);

        return response()->json(['data' => $this->transform($lista, true)]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->abortUnlessAdmin($request);

        $data = $this->validated($request);
        $lista = Lista::create($data);

        AuditLog::record('lista.created', "Lista \"{$lista->nome}\" criada via API", $lista->empresa_id, 'lista', $lista->id);

        return response()->json(['data' => $this->transform($lista, true)], 201);
    }

    public function update(Request $request, Lista $lista): JsonResponse
    {
        $this->abortUnlessAdmin($request);

        $data = $this->validated($request);
        $lista->update($data);

        AuditLog::record('lista.updated', "Lista \"{$lista->nome}\" atualizada via API", $lista->empresa_id, 'lista', $lista->id);

        return response()->json(['data' => $this->transform($lista->fresh(), true)]);
    }

    private function authorizeAccess(Request $request, Lista $lista): void
    {
        if ($this->isAdmin($request)) {
            return;
        }

        if ($lista->empresa_id !== null && $lista->empresa_id !== $this->empresaId($request)) {
            abort(403, 'Você não tem acesso a esta lista.');
        }
    }

    private function transform(Lista $lista, bool $detalhado = false): array
    {
        $base = [
            'id' => $lista->id,
            'nome' => $lista->nome,
            'descricao' => $lista->descricao,
            'status' => $lista->status,
            'origem' => $lista->origem,
            'empresa_id' => $lista->empresa_id,
        ];

        if ($detalhado) {
            $base['dominios_count'] = $lista->dominios()->count();
            $base['dominios_ativos_count'] = $lista->dominios()->where('ativo', true)->count();
            if ($lista->isExterna()) {
                $base['fonte_url'] = $lista->fonte_url;
                $base['fonte_formato'] = $lista->fonte_formato;
                $base['sync_ativo'] = $lista->sync_ativo;
                $base['last_sync_at'] = $lista->last_sync_at?->toIso8601String();
            }
        }

        return $base;
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'empresa_id' => ['nullable', 'exists:empresas,id'],
            'nome' => ['required', 'string', 'max:255'],
            'descricao' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],
            'origem' => ['nullable', 'in:manual,externa'],
            'fonte_url' => ['nullable', 'url', 'max:500', 'required_if:origem,externa'],
            'fonte_formato' => ['nullable', 'in:hostfile,plain'],
        ]);

        $data['origem'] = $data['origem'] ?? 'manual';

        if ($data['origem'] === 'externa') {
            $data['fonte_formato'] = $data['fonte_formato'] ?? 'hostfile';
            $data['fonte_externa'] = 'custom';
            $data['sync_ativo'] = true;
        } else {
            $data['fonte_url'] = null;
            $data['fonte_externa'] = null;
        }

        return $data;
    }
}
