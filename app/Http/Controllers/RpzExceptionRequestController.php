<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\EmpresaDomainException;
use App\Models\User;
use App\Services\CompanyRpzExceptions;
use App\Services\RpzExceptionPolicy;
use App\Services\RpzListLinkConfirmation;
use App\Services\RpzManualDomainRequests;
use App\Services\RpzManualListRequests;
use App\Services\RpzSynchronizationStatus;
use App\Services\RpzZoneBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RpzExceptionRequestController extends Controller
{
    public function diagnose(Request $request, int $empresa, CompanyRpzExceptions $exceptions, RpzZoneBuilder $builder): JsonResponse
    {
        $data = $request->validate(['dominio' => ['required', 'string', 'max:253'], 'page' => ['sometimes', 'integer', 'min:1']]);
        $company = Empresa::query()->findOrFail($empresa);
        $domain = $exceptions->normalize($data['dominio']);
        $candidates = [$domain];
        $labels = explode('.', $domain);
        for ($index = 1; $index < count($labels) - 1; $index++) {
            $ancestor = implode('.', array_slice($labels, $index));
            if ($ancestor !== 'wpad.domain.name') {
                $candidates[] = $ancestor;
            }
        }
        $policy = new RpzExceptionPolicy($company->id, $builder->panelHost());
        $rules = $builder->companyQuery($company->id)
            ->whereIn(DB::raw('LOWER(dominios.dominio)'), $candidates)
            ->reorder()
            ->select('listas.id', 'listas.nome', 'listas.origem', 'dominios.dominio');
        $lists = DB::query()->fromSub($rules, 'candidate_rules')
            ->orderBy('id')->orderBy('dominio')->paginate(15)->withQueryString();

        return response()->json([
            'empresa' => $company->only(['id', 'nome']),
            'dominio' => $domain,
            'excecao_aplicavel' => $policy->allows($domain),
        ] + $lists->toArray());
    }

    public function index(Request $request, int $empresa): JsonResponse
    {
        $request->validate(['page' => ['sometimes', 'integer', 'min:1']]);
        $company = Empresa::query()->findOrFail($empresa);
        $exceptions = EmpresaDomainException::where('empresa_id', $company->id)->orderBy('dominio')
            ->paginate(15, ['id', 'dominio', 'motivo', 'created_at']);

        return response()->json(['empresa' => $company->only(['id', 'nome'])] + $exceptions->toArray());
    }

    public function prepare(Request $request, int $empresa, CompanyRpzExceptions $exceptions): JsonResponse
    {
        $data = $request->validate([
            'dominio' => ['required', 'string', 'max:253'],
            'motivo' => ['required', 'string', 'max:255'],
            'operacao' => ['sometimes', 'in:criar,remover'],
        ]);
        $company = Empresa::query()->findOrFail($empresa);
        $domain = $exceptions->normalize($data['dominio']);
        $operation = $data['operacao'] ?? 'criar';
        $target = $operation === 'remover'
            ? EmpresaDomainException::where('empresa_id', $company->id)->where('dominio', $domain)->firstOrFail()
            : null;
        $id = (string) Str::uuid();
        $expires = now()->addMinutes(10);
        DB::table('rpz_exception_requests')->insert([
            'id' => $id,
            'empresa_id' => $company->id,
            'dominio' => $domain,
            'motivo' => $data['motivo'],
            'expires_at' => $expires,
            'remove_exception_id' => $target?->id,
        ]);

        return response()->json(['data' => [
            'solicitacao' => $id,
            'operacao' => $operation,
            'excecao_id' => $target?->id,
            'empresa_id' => $company->id,
            'empresa' => $company->nome,
            'dominio' => $domain,
            'inclui_subdominios' => true,
            'motivo' => $data['motivo'],
            'expira_em' => $expires->toISOString(),
        ]], 201);
    }

    public function prepareManualDomain(Request $request, int $empresa, int $lista, RpzManualDomainRequests $domains): JsonResponse
    {
        $input = $request->validate([
            'dominio' => ['required', 'string', 'max:255'],
            'operacao' => ['required', 'in:incluir,remover'],
            'motivo' => ['required', 'string', 'max:255'],
        ]);

        return response()->json(['data' => $domains->prepare($empresa, $lista, $input)], 201)
            ->header('Cache-Control', 'private, no-store');
    }

    public function prepareManualList(Request $request, int $empresa, RpzManualListRequests $lists): JsonResponse
    {
        $input = $request->validate([
            'operacao' => ['required', 'in:cadastrar,alterar,remover'],
            'lista_id' => ['required_if:operacao,alterar,remover', 'prohibited_if:operacao,cadastrar', 'integer', 'min:1'],
            'nome' => ['required_if:operacao,cadastrar', 'prohibited_if:operacao,remover', 'filled', 'string', 'max:255'],
            'descricao' => ['prohibited_if:operacao,remover', 'nullable', 'string'],
            'status' => ['prohibited_if:operacao,remover', 'in:active,inactive'],
            'motivo' => ['required', 'string', 'max:255'],
        ]);

        return response()->json(['data' => $lists->prepare($empresa, $input)], 201);
    }

    public function confirm(
        Request $request,
        string $id,
        CompanyRpzExceptions $exceptions,
        RpzSynchronizationStatus $status,
        RpzListLinkConfirmation $links,
        RpzManualDomainRequests $domains,
        RpzManualListRequests $lists,
    ): JsonResponse
    {
        $token = $request->bearerToken();
        $expected = config('services.rpz_telegram.confirmation_hash');
        abort_unless(
            is_string($expected) && is_string($token) && preg_match('/^rpza_[a-f0-9]{64}$/D', $token)
            && hash_equals($expected, hash('sha256', $token)),
            401,
            'Credencial de confirmação inválida.',
        );

        $data = $request->validate([
            'telegram_user_id' => ['required', 'string', 'regex:/^\d{1,20}$/D'],
            'telegram_chat_id' => ['required', 'string', 'regex:/^-?\d{1,20}$/D'],
            'telegram_thread_id' => ['required', 'string', 'regex:/^\d{1,20}$/D'],
        ]);
        $routes = config('services.rpz_telegram');
        abort_unless(
            $data['telegram_chat_id'] === $routes['chat_id']
            && $data['telegram_thread_id'] === $routes['thread_id'],
            403,
        );
        $operators = json_decode((string) $routes['operators'], true);
        $userId = $operators[$data['telegram_user_id']] ?? null;
        $operator = $userId === null ? null : User::query()->find($userId);
        abort_unless($operator?->isAdmin(), 403, 'Operador sem permissão administrativa.');
        Auth::setUser($operator);

        if (DB::table('rpz_manual_list_requests')->where('id', $id)->exists()) {
            return response()->json(['data' => $lists->confirm($id, $operator)])
                ->header('Cache-Control', 'private, no-store');
        }

        if (DB::table('rpz_manual_domain_requests')->where('id', $id)->exists()) {
            $receipt = $domains->confirm($id, $operator);
            if ($receipt['servidores_vinculados'] !== []) {
                $receipt['publicacao_empresa'] = $status->company((int) $receipt['empresa_id']);
            }

            return response()->json(['data' => $receipt])->header('Cache-Control', 'private, no-store');
        }

        if (DB::table('rpz_list_link_requests')->where('id', $id)->exists()) {
            $receipt = $links->confirm($id, $operator);
            $receipt['publicacao_empresa'] = $status->company((int) $receipt['empresa_id']);

            return response()->json(['data' => $receipt])->header('Cache-Control', 'private, no-store');
        }

        $receipt = DB::transaction(function () use ($id, $operator, $exceptions): array {
            $requests = DB::table('rpz_exception_requests');
            $claimed = (clone $requests)->where('id', $id)->whereNull('confirmed_at')
                ->where('expires_at', '>', now())->update([
                    'confirmed_at' => now(),
                    'confirmed_by' => $operator->id,
                ]);
            $proposal = $requests->where('id', $id)->first();
            abort_if($proposal === null, 404);
            if ($claimed === 0) {
                abort_unless($proposal->exception_id !== null && (int) $proposal->confirmed_by === $operator->id, 409, 'Solicitação expirada ou já processada.');
            } else {
                $company = Empresa::query()->findOrFail($proposal->empresa_id);
                if ($proposal->remove_exception_id === null) {
                    $exceptionId = $exceptions->create($company, $proposal->dominio, $proposal->motivo, $id)->id;
                } else {
                    $exceptionId = (int) $proposal->remove_exception_id;
                    $exceptions->remove($company, $exceptionId, $id, $proposal->motivo);
                }
                $requests->update(['exception_id' => $exceptionId]);
                $proposal->exception_id = $exceptionId;
            }

            return [
                'solicitacao' => $id,
                'excecao_id' => $proposal->exception_id,
                'operacao' => $proposal->remove_exception_id === null ? 'criar' : 'remover',
                'empresa_id' => $proposal->empresa_id,
                'dominio' => $proposal->dominio,
                'confirmada_em' => Carbon::parse($proposal->confirmed_at)->toISOString(),
            ];
        });

        $receipt['empresa'] = Empresa::query()->whereKey($receipt['empresa_id'])->value('nome');
        $receipt['publicacao_empresa'] = $status->company((int) $receipt['empresa_id']);

        return response()->json(['data' => $receipt])->header('Cache-Control', 'private, no-store');
    }
}
