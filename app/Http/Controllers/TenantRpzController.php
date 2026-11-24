<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Dominio;
use App\Models\Lista;
use App\Models\Servidor;
use App\Services\DomainNormalizer;
use App\Services\RpzDirtyMarker;
use App\Services\RpzSynchronizationStatus;
use App\Services\RpzZoneBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class TenantRpzController extends Controller
{
    public function __construct(private DomainNormalizer $normalizer, private RpzDirtyMarker $dirty) {}

    public function listas(Request $request): JsonResponse
    {
        $id = $request->user()->empresa_id;
        $lists = $this->visibleLists($id)->orderBy('id')->paginate($this->perPage($request));

        return response()->json($lists->through(fn (Lista $list) => [
            'id' => $list->id,
            'nome' => $list->nome,
            'origem' => $list->origem,
            'status' => $list->status,
            'editavel' => $list->empresa_id === $id && $list->origem === 'manual' && $list->tenant_api_write,
        ]));
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'dominio' => ['sometimes', 'required', 'string', 'max:255'],
            'ativo' => ['sometimes', 'required', 'boolean'],
            'lista_id' => ['sometimes', 'required', 'integer', 'min:1'],
        ]);

        $rules = $this->visibleRules($request->user()->empresa_id)
            ->when(isset($filters['dominio']), fn (Builder $query) => $query->where('dominio', $this->normalize($filters['dominio'])))
            ->when(isset($filters['ativo']), fn (Builder $query) => $query->where('ativo', (bool) $filters['ativo']))
            ->when(isset($filters['lista_id']), fn (Builder $query) => $query->where('lista_id', $filters['lista_id']))
            ->orderBy('id')->paginate($this->perPage($request))->withQueryString();

        return response()->json($rules->through($this->rule(...)));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json(['data' => $this->rule($this->visibleRules($request->user()->empresa_id)->findOrFail($id))]);
    }

    public function store(Request $request, int $lista): Response
    {
        $data = $request->validate(['dominio' => ['required', 'string', 'max:255']]);
        $domain = $this->normalize($data['dominio']);
        $company = $request->user()->empresa_id;

        try {
            $rule = DB::transaction(function () use ($request, $lista, $domain, $company) {
                $list = $this->writableList($company, $lista);
                $rule = $list->dominios()->create(['dominio' => $domain, 'ativo' => true]);
                $this->recordChange($request, $rule, 'created');

                return $rule;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw $this->duplicate();
        }

        return $this->mutationResponse($request, $rule, 201);
    }

    public function update(Request $request, int $id): Response
    {
        $data = $request->validate([
            'dominio' => ['sometimes', 'required', 'string', 'max:255'],
            'ativo' => ['sometimes', 'required', 'boolean'],
        ]);
        if ($data === []) {
            throw ValidationException::withMessages(['dominio' => 'Informe dominio ou ativo.']);
        }
        if (isset($data['dominio'])) {
            $data['dominio'] = $this->normalize($data['dominio']);
        }

        try {
            $rule = DB::transaction(function () use ($request, $id, $data) {
                $rule = $this->writableRule($request, $id);
                $rule->fill($data);
                if ($rule->isDirty()) {
                    $rule->save();
                    $this->recordChange($request, $rule, 'updated');
                }

                return $rule;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw $this->duplicate();
        }

        return $this->mutationResponse($request, $rule, 200);
    }

    public function destroy(Request $request, int $id): Response
    {
        DB::transaction(function () use ($request, $id) {
            $rule = $this->writableRule($request, $id);
            $rule->delete();
            $this->recordChange($request, $rule, 'destroyed');
        }, 3);

        return $request->is('api/*') ? response()->noContent() : back()->with('status', 'Bloqueio removido.');
    }

    public function publicacao(Request $request, RpzSynchronizationStatus $status): JsonResponse
    {
        return response()->json(['data' => $status->company($request->user()->empresa_id)]);
    }

    public function servidores(Request $request, RpzSynchronizationStatus $status): JsonResponse
    {
        return response()->json($status->servers($request->user()->empresa_id, $this->perPage($request)));
    }

    public function confirmar(Request $request): Response
    {
        $data = $request->validate([
            'serial' => ['required', 'integer', 'between:0,4294967295'],
            'zona' => ['required', 'in:empresa,servidor'],
        ]);
        $company = $request->user()->empresa_id;
        DB::transaction(function () use ($request, $data, $company): void {
            $server = Servidor::where('empresa_id', $company)->where('status', 'active')
                ->whereKey($request->attributes->get('tenant_api_server_id'))->lockForUpdate()->firstOrFail();
            DB::table('tenant_rpz_reports')->upsert([[
                'servidor_id' => $server->id,
                'empresa_id' => $company,
                'serial' => $data['serial'],
                'zone_scope' => $data['zona'],
                'reported_at' => now(),
            ]], ['servidor_id', 'empresa_id'], ['serial', 'zone_scope', 'reported_at']);
        }, 3);

        return response()->noContent();
    }

    private function visibleLists(int $company): Builder
    {
        return Lista::query()->where(function (Builder $query) use ($company) {
            $query->where('empresa_id', $company)->orWhere(function (Builder $shared) use ($company) {
                $shared->whereNull('empresa_id')->whereHas('servidores', fn (Builder $servers) => $servers->where('servidores.empresa_id', $company));
            });
        });
    }

    private function visibleRules(int $company): Builder
    {
        return Dominio::whereIn('lista_id', $this->visibleLists($company)->select('id'));
    }

    private function writableList(int $company, int $id): Lista
    {
        $list = Lista::where('empresa_id', $company)->lockForUpdate()->findOrFail($id);
        abort_unless($list->origem === 'manual' && $list->tenant_api_write, 403, 'Esta fonte não está habilitada para edição pelo cliente.');
        abort_if($list->servidores()->where(fn (Builder $servers) => $servers
            ->whereNull('servidores.empresa_id')->orWhere('servidores.empresa_id', '!=', $company))->exists(), 409, 'A fonte possui vínculos fora da empresa.');

        return $list;
    }

    private function writableRule(Request $request, int $id): Dominio
    {
        $company = $request->user()->empresa_id;
        $rule = $this->visibleRules($company)->findOrFail($id);
        $this->writableList($company, $rule->lista_id);

        return Dominio::where('lista_id', $rule->lista_id)->lockForUpdate()->findOrFail($id);
    }

    private function normalize(string $domain): string
    {
        $normalized = $this->normalizer->normalize($domain, false);
        if ($normalized === null || $normalized === app(RpzZoneBuilder::class)->panelHost()) {
            throw ValidationException::withMessages(['dominio' => 'Informe um domínio válido para bloqueio.']);
        }

        return $normalized;
    }

    private function perPage(Request $request): int
    {
        $data = $request->validate(['per_page' => ['sometimes', 'required', 'integer', 'between:1,100'], 'page' => ['sometimes', 'required', 'integer', 'min:1']]);

        return (int) ($data['per_page'] ?? 50);
    }

    private function rule(Dominio $rule): array
    {
        return $rule->only(['id', 'lista_id', 'dominio', 'ativo']);
    }

    private function recordChange(Request $request, Dominio $rule, string $action): void
    {
        $credential = $request->attributes->get('tenant_api_credential_id');
        $origin = $credential ? "API #{$credential}" : 'Painel do cliente';
        AuditLog::record('dominio.'.$action, "{$origin} · {$rule->dominio}", $request->user()->empresa_id, 'dominio', $rule->id);
        $this->dirty->markForList($rule->lista_id);
    }

    private function duplicate(): ConflictHttpException
    {
        return new ConflictHttpException('O domínio já existe nessa fonte.');
    }

    private function mutationResponse(Request $request, Dominio $rule, int $status): Response
    {
        if (! $request->is('api/*')) {
            return back()->with('status', 'Bloqueio salvo.');
        }

        $response = response()->json(['data' => $this->rule($rule)], $status);
        if ($status === 201) {
            $response->headers->set('Location', route('tenant-api.bloqueios.show', $rule->id));
        }

        return $response;
    }
}
