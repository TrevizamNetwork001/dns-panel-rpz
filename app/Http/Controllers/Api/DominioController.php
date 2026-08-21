<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\HasApiActor;
use App\Http\Controllers\Controller;
use App\Models\Dominio;
use App\Models\Lista;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DominioController extends Controller
{
    use HasApiActor;

    private const DOMAIN_REGEX = '/^(?!-)[a-z0-9-]{1,63}(?<!-)(\.[a-z0-9-]{1,63})*\.[a-z]{2,63}$/i';

    public function index(Request $request, Lista $lista): JsonResponse
    {
        $this->authorizeListaAccess($request, $lista);

        $dominios = $lista->dominios()->orderBy('dominio')->paginate(min((int) $request->input('per_page', 50), 200));

        return response()->json([
            'data' => $dominios->getCollection()->map(fn (Dominio $d) => $this->transform($d)),
            'meta' => [
                'current_page' => $dominios->currentPage(),
                'last_page' => $dominios->lastPage(),
                'total' => $dominios->total(),
            ],
        ]);
    }

    public function store(Request $request, Lista $lista): JsonResponse
    {
        $this->abortUnlessAdmin($request);

        if ($lista->isExterna()) {
            return response()->json(['message' => 'Esta lista é sincronizada de uma fonte externa e não pode ser editada manualmente.'], 422);
        }

        $data = $request->validate(['dominio' => ['required', 'string', 'max:255']]);

        $normalized = $this->normalize($data['dominio']);
        if ($normalized === null) {
            return response()->json(['message' => 'Domínio inválido.', 'errors' => ['dominio' => ['Domínio inválido.']]], 422);
        }

        $dominio = $lista->dominios()->firstOrCreate(['dominio' => $normalized], ['ativo' => true]);

        return response()->json(['data' => $this->transform($dominio)], 201);
    }

    public function bulkStore(Request $request, Lista $lista): JsonResponse
    {
        $this->abortUnlessAdmin($request);

        if ($lista->isExterna()) {
            return response()->json(['message' => 'Esta lista é sincronizada de uma fonte externa e não pode ser editada manualmente.'], 422);
        }

        $data = $request->validate(['dominios' => ['required', 'array', 'max:5000'], 'dominios.*' => ['string']]);

        $existentes = $lista->dominios()->pluck('dominio')->flip();
        $added = 0;
        $duplicated = 0;
        $invalid = 0;

        foreach (array_unique($data['dominios']) as $linha) {
            $normalized = $this->normalize($linha);

            if ($normalized === null) {
                $invalid++;
                continue;
            }

            if ($existentes->has($normalized)) {
                $duplicated++;
                continue;
            }

            $lista->dominios()->create(['dominio' => $normalized, 'ativo' => true]);
            $existentes->put($normalized, true);
            $added++;
        }

        return response()->json(['data' => ['adicionados' => $added, 'duplicados' => $duplicated, 'invalidos' => $invalid]], 201);
    }

    public function toggle(Request $request, Dominio $dominio): JsonResponse
    {
        $this->abortUnlessAdmin($request);

        if ($dominio->lista->isExterna()) {
            return response()->json(['message' => 'Esta lista é sincronizada de uma fonte externa e não pode ser editada manualmente.'], 422);
        }

        $dominio->update(['ativo' => ! $dominio->ativo]);

        return response()->json(['data' => $this->transform($dominio)]);
    }

    public function destroy(Request $request, Dominio $dominio): JsonResponse
    {
        $this->abortUnlessAdmin($request);

        if ($dominio->lista->isExterna()) {
            return response()->json(['message' => 'Esta lista é sincronizada de uma fonte externa e não pode ser editada manualmente.'], 422);
        }

        $dominio->delete();

        return response()->json(null, 204);
    }

    private function authorizeListaAccess(Request $request, Lista $lista): void
    {
        if ($this->isAdmin($request)) {
            return;
        }

        if ($lista->empresa_id !== null && $lista->empresa_id !== $this->empresaId($request)) {
            abort(403, 'Você não tem acesso a esta lista.');
        }
    }

    private function transform(Dominio $dominio): array
    {
        return [
            'id' => $dominio->id,
            'lista_id' => $dominio->lista_id,
            'dominio' => $dominio->dominio,
            'ativo' => $dominio->ativo,
        ];
    }

    private function normalize(string $value): ?string
    {
        $value = strtolower(trim($value));
        $value = rtrim($value, '.');

        if ($value === '' || ! preg_match(self::DOMAIN_REGEX, $value)) {
            return null;
        }

        return $value;
    }
}
