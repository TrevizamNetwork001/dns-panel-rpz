<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\HasApiActor;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Licenca;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LicencaController extends Controller
{
    use HasApiActor;

    public function index(Request $request): JsonResponse
    {
        $query = Licenca::with('empresa');

        if (! $this->isAdmin($request)) {
            $query->where('empresa_id', $this->empresaId($request));
        }

        $licencas = $query->orderByDesc('id')->paginate(min((int) $request->input('per_page', 20), 100));

        return response()->json([
            'data' => $licencas->getCollection()->map(fn (Licenca $l) => $this->transform($l)),
            'meta' => [
                'current_page' => $licencas->currentPage(),
                'last_page' => $licencas->lastPage(),
                'total' => $licencas->total(),
            ],
        ]);
    }

    public function show(Request $request, Licenca $licenca): JsonResponse
    {
        $this->abortUnlessOwnerOrAdmin($request, $licenca->empresa_id);

        return response()->json(['data' => $this->transform($licenca)]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->abortUnlessAdmin($request);

        $data = $this->validated($request);
        $licenca = Licenca::create($data);

        AuditLog::record('licenca.created', "Licença criada via API para empresa #{$licenca->empresa_id}", $licenca->empresa_id, 'licenca', $licenca->id);

        return response()->json(['data' => $this->transform($licenca)], 201);
    }

    public function update(Request $request, Licenca $licenca): JsonResponse
    {
        $this->abortUnlessAdmin($request);

        $data = $this->validated($request);
        $licenca->update($data);

        AuditLog::record('licenca.updated', "Licença #{$licenca->id} atualizada via API", $licenca->empresa_id, 'licenca', $licenca->id);

        return response()->json(['data' => $this->transform($licenca->fresh())]);
    }

    public function destroy(Request $request, Licenca $licenca): JsonResponse
    {
        $this->abortUnlessAdmin($request);

        AuditLog::record('licenca.destroyed', "Licença #{$licenca->id} removida via API", $licenca->empresa_id, 'licenca', $licenca->id);

        $licenca->delete();

        return response()->json(null, 204);
    }

    private function transform(Licenca $licenca): array
    {
        return [
            'id' => $licenca->id,
            'empresa_id' => $licenca->empresa_id,
            'empresa_nome' => $licenca->empresa?->nome,
            'starts_at' => $licenca->starts_at?->toDateString(),
            'expires_at' => $licenca->expires_at?->toDateString(),
            'max_servidores' => $licenca->max_servidores,
            'status' => $licenca->status,
        ];
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'empresa_id' => ['required', 'exists:empresas,id'],
            'starts_at' => ['required', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'max_servidores' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'in:active,inactive'],
        ]);
    }
}
