<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Dominio;
use App\Models\Lista;
use App\Models\Servidor;
use App\Models\TenantApiCredential;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class TenantIntegrationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $company = $user->empresa_id;
        $credentials = TenantApiCredential::where('user_id', $user->id)->where('empresa_id', $company)
            ->latest('id')->paginate(15, ['id', 'name', 'scope', 'expires_at', 'servidor_id'], 'credenciais');
        $servers = Servidor::where('empresa_id', $company)->where('status', 'active')->orderBy('nome')->get(['id', 'nome']);
        $lists = Lista::where('empresa_id', $company)->where('origem', 'manual')->where('tenant_api_write', true)
            ->whereDoesntHave('servidores', fn (Builder $query) => $query->where(fn (Builder $servers) => $servers
                ->whereNull('servidores.empresa_id')->orWhere('servidores.empresa_id', '!=', $company)))
            ->orderBy('nome')->get(['id', 'nome']);
        $rules = Dominio::whereIn('lista_id', $lists->modelKeys())->orderByDesc('id')
            ->paginate(15, ['id', 'lista_id', 'dominio', 'ativo'], 'bloqueios')->withQueryString();
        $encryptedToken = $request->session()->pull('tenant_api_new_token');
        $token = $encryptedToken === null ? null : Crypt::decryptString($encryptedToken);

        return response()->view('integracoes.index', compact('credentials', 'servers', 'lists', 'rules', 'token'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'scope' => ['required', 'in:read,write,report'],
            'server_id' => ['nullable', 'required_if:scope,report', 'integer'],
        ]);
        $user = $request->user();
        $server = $data['scope'] === 'report'
            ? Servidor::where('empresa_id', $user->empresa_id)->where('status', 'active')->find($data['server_id'])
            : null;
        if ($data['scope'] === 'report' && $server === null) {
            throw ValidationException::withMessages(['server_id' => 'Selecione um servidor ativo da empresa.']);
        }

        $token = 'rpzk_'.Str::random(64);
        DB::transaction(function () use ($user, $data, $token, $server): void {
            $credential = TenantApiCredential::create([
                'empresa_id' => $user->empresa_id,
                'user_id' => $user->id,
                'servidor_id' => $server?->id,
                'name' => $data['name'],
                'scope' => $data['scope'],
                'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addDays(90),
            ]);
            AuditLog::record('auth.api_credential_created', 'Credencial de integração criada', $user->empresa_id, 'api_credential', $credential->id);
        });

        return redirect()->route('integracoes.index')->with('tenant_api_new_token', Crypt::encryptString($token));
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        DB::transaction(function () use ($user, $id): void {
            $credential = TenantApiCredential::where('empresa_id', $user->empresa_id)
                ->where('user_id', $user->id)->lockForUpdate()->findOrFail($id);
            $credential->delete();
            AuditLog::record('auth.api_credential_revoked', 'Credencial de integração revogada', $user->empresa_id, 'api_credential', $id);
        });

        return back()->with('status', 'Credencial revogada.');
    }

    public function openapi(): Response
    {
        return response()->file(resource_path('openapi/tenant-rpz.json'), ['Content-Type' => 'application/json']);
    }
}
