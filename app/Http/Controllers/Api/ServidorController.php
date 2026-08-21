<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\HasApiActor;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Empresa;
use App\Models\Lista;
use App\Models\Servidor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServidorController extends Controller
{
    use HasApiActor;

    public function index(Request $request): JsonResponse
    {
        $query = Servidor::with(['empresa', 'listas']);

        if (! $this->isAdmin($request)) {
            $query->where('empresa_id', $this->empresaId($request));
        }

        $servidores = $query->orderByDesc('id')->paginate(min((int) $request->input('per_page', 20), 100));

        return response()->json([
            'data' => $servidores->getCollection()->map(fn (Servidor $s) => $this->transform($s)),
            'meta' => [
                'current_page' => $servidores->currentPage(),
                'last_page' => $servidores->lastPage(),
                'total' => $servidores->total(),
            ],
        ]);
    }

    public function show(Request $request, Servidor $servidor): JsonResponse
    {
        $this->abortUnlessOwnerOrAdmin($request, $servidor->empresa_id);

        return response()->json(['data' => $this->transform($servidor->load(['empresa', 'listas', 'allowedIps']), true)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        if (! $this->isAdmin($request)) {
            $data['empresa_id'] = $this->empresaId($request);

            if ($blocker = $this->licencaBlocker($data['empresa_id'])) {
                return response()->json(['message' => $blocker], 422);
            }
        } elseif (empty($data['empresa_id'])) {
            return response()->json(['message' => 'empresa_id é obrigatório.', 'errors' => ['empresa_id' => ['O campo empresa_id é obrigatório.']]], 422);
        }

        $servidor = Servidor::create($data);
        $servidor->listas()->sync($request->input('lista_ids', []));

        AuditLog::record('servidor.created', "Servidor \"{$servidor->nome}\" criado via API", $servidor->empresa_id, 'servidor', $servidor->id);

        return response()->json(['data' => $this->transform($servidor->fresh(['empresa', 'listas']), true)], 201);
    }

    public function update(Request $request, Servidor $servidor): JsonResponse
    {
        $this->abortUnlessOwnerOrAdmin($request, $servidor->empresa_id);

        $data = $this->validated($request);

        if (! $this->isAdmin($request)) {
            $data['empresa_id'] = $servidor->empresa_id;
        }

        $servidor->update($data);

        if ($request->has('lista_ids')) {
            $servidor->listas()->sync($request->input('lista_ids', []));
        }

        AuditLog::record('servidor.updated', "Servidor \"{$servidor->nome}\" atualizado via API", $servidor->empresa_id, 'servidor', $servidor->id);

        return response()->json(['data' => $this->transform($servidor->fresh(['empresa', 'listas']), true)]);
    }

    public function destroy(Request $request, Servidor $servidor): JsonResponse
    {
        $this->abortUnlessOwnerOrAdmin($request, $servidor->empresa_id);

        AuditLog::record('servidor.destroyed', "Servidor \"{$servidor->nome}\" removido via API", $servidor->empresa_id, 'servidor', $servidor->id);

        $servidor->delete();

        return response()->json(null, 204);
    }

    private function transform(Servidor $servidor, bool $detalhado = false): array
    {
        $base = [
            'id' => $servidor->id,
            'nome' => $servidor->nome,
            'status' => $servidor->status,
            'tipo_dns' => $servidor->tipo_dns,
            'bloqueio_modo' => $servidor->bloqueio_modo,
            'ip_v4' => $servidor->ip_v4,
            'ip_v6' => $servidor->ip_v6,
            'empresa_id' => $servidor->empresa_id,
            'empresa_nome' => $servidor->empresa?->nome,
            'last_synced_at' => $servidor->last_synced_at?->toIso8601String(),
            'listas' => $servidor->listas->map(fn (Lista $l) => ['id' => $l->id, 'nome' => $l->nome])->all(),
        ];

        if ($detalhado) {
            $base['token'] = $servidor->token;
            $base['rpz_url'] = url('/rpz/' . $servidor->token . '.zone');
            $base['ip_restriction_enabled'] = $servidor->ip_restriction_enabled;
        }

        return $base;
    }

    private function licencaBlocker(?int $empresaId): ?string
    {
        $empresa = Empresa::find($empresaId);

        if (! $empresa) {
            return 'Empresa não configurada corretamente.';
        }

        $capacidade = $empresa->licencas()
            ->where('status', 'active')
            ->whereDate('starts_at', '<=', now())
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhereDate('expires_at', '>=', now());
            })
            ->sum('max_servidores');

        if ($capacidade === 0) {
            return 'Sua empresa não possui licença ativa.';
        }

        if ($empresa->servidores()->count() >= $capacidade) {
            return "Limite de servidores da licença atingido ({$empresa->servidores()->count()}/{$capacidade}).";
        }

        return null;
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:active,inactive'],
            'tipo_dns' => ['sometimes', 'in:unbound,bind9,outro'],
            'bloqueio_modo' => ['sometimes', 'in:nxdomain,redirect'],
            'ip_v4' => ['nullable', 'ip'],
            'ip_v6' => ['nullable', 'ip'],
            'empresa_id' => ['sometimes', 'exists:empresas,id'],
        ]);
    }
}
