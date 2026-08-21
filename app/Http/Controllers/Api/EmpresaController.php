<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\HasApiActor;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Empresa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmpresaController extends Controller
{
    use HasApiActor;

    public function index(Request $request): JsonResponse
    {
        if ($this->isAdmin($request)) {
            $empresas = Empresa::orderBy('nome')->paginate(min((int) $request->input('per_page', 20), 100));

            return response()->json([
                'data' => $empresas->getCollection()->map(fn (Empresa $e) => $this->transform($e)),
                'meta' => [
                    'current_page' => $empresas->currentPage(),
                    'last_page' => $empresas->lastPage(),
                    'total' => $empresas->total(),
                ],
            ]);
        }

        $empresa = Empresa::find($this->empresaId($request));

        return response()->json(['data' => $empresa ? [$this->transform($empresa)] : []]);
    }

    public function show(Request $request, Empresa $empresa): JsonResponse
    {
        $this->abortUnlessOwnerOrAdmin($request, $empresa->id);

        return response()->json(['data' => $this->transform($empresa)]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->abortUnlessAdmin($request);

        $data = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'documento' => ['nullable', 'string', 'max:32'],
            'email_contato' => ['nullable', 'email', 'max:255'],
            'status' => ['required', 'in:pending,active,inactive'],
        ]);

        $empresa = Empresa::create($data);

        AuditLog::record('empresa.created', "Empresa \"{$empresa->nome}\" criada via API", $empresa->id, 'empresa', $empresa->id);

        return response()->json(['data' => $this->transform($empresa)], 201);
    }

    public function update(Request $request, Empresa $empresa): JsonResponse
    {
        $this->abortUnlessAdmin($request);

        $data = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'documento' => ['nullable', 'string', 'max:32'],
            'email_contato' => ['nullable', 'email', 'max:255'],
            'status' => ['required', 'in:pending,active,inactive'],
        ]);

        $empresa->update($data);

        AuditLog::record('empresa.updated', "Empresa \"{$empresa->nome}\" atualizada via API", $empresa->id, 'empresa', $empresa->id);

        return response()->json(['data' => $this->transform($empresa->fresh())]);
    }

    private function transform(Empresa $empresa): array
    {
        return [
            'id' => $empresa->id,
            'nome' => $empresa->nome,
            'documento' => $empresa->documento,
            'email_contato' => $empresa->email_contato,
            'status' => $empresa->status,
        ];
    }
}
