<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Empresa;
use App\Models\Lista;
use App\Models\RpzZone;
use App\Models\ServerAllowedIp;
use App\Models\Servidor;
use App\Services\RpzDirtyMarker;
use App\Services\TelegramNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ServidorController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();

        $query = Servidor::with(['empresa', 'listas'])
            ->withCount(['allowedIps as ips_pendentes_count' => fn ($ips) => $ips->where('status', 'pending')]);

        if ($user->isAdmin()) {
            $query->orderByRaw('last_synced_at IS NULL ASC')->orderByDesc('last_synced_at');
        }
        $query->orderByDesc('id');

        if ($user->isCliente()) {
            $query->where('empresa_id', $user->empresa_id);
        }

        $servidoresAtivos = (clone $query)->where('status', 'active')->count();
        $servidores = $query->paginate(20);

        return view('servidores.index', compact('servidores', 'servidoresAtivos'));
    }

    public function create(): View
    {
        $user = Auth::user();
        $servidor = new Servidor;
        $listasDisponiveis = collect();

        if ($user->isCliente()) {
            $empresas = collect([$user->empresa])->filter();
            $licencaBlocker = $this->licencaBlocker($user->empresa);
            if ($user->empresa_id) {
                $listasDisponiveis = $this->listasParaEmpresa($user->empresa_id);
            }
        } else {
            $empresas = Empresa::orderBy('nome')->get();
            $empresaSelecionada = $empresas->firstWhere('id', request('empresa_id'));
            if ($empresaSelecionada) {
                $servidor->empresa_id = $empresaSelecionada->id;
                $listasDisponiveis = $this->listasParaEmpresa($empresaSelecionada->id);
            }
            $licencaBlocker = null;
        }

        return view('servidores.form', compact('servidor', 'empresas', 'licencaBlocker', 'listasDisponiveis'));
    }

    public function store(Request $request, TelegramNotifier $telegram, RpzDirtyMarker $rpzDirty): RedirectResponse
    {
        $user = Auth::user();
        $data = $this->validated($request, $user, exigirIp: $user->isCliente());
        if ($data['tipo_dns'] === 'mikrotik') {
            $data['bloqueio_modo'] = 'nxdomain';
        }
        $listaIds = $data['lista_ids'] ?? [];
        unset($data['lista_ids']);

        if ($user->isCliente()) {
            $data['empresa_id'] = $user->empresa_id;

            if ($blocker = $this->licencaBlocker($user->empresa)) {
                return back()->withErrors(['empresa_id' => $blocker])->withInput();
            }
        }

        $servidor = Servidor::create($data);
        $servidor->listas()->sync($listaIds);
        $rpzDirty->markForServer($servidor->id);

        AuditLog::record('servidor.created', "Servidor \"{$servidor->nome}\" criado", $servidor->empresa_id, 'servidor', $servidor->id);

        $pendentes = $user->isCliente() ? $this->registrarIpsPendentes($servidor, $telegram) : [];

        $liberados = $user->isAdmin() ? $this->liberarIpsInformados($servidor) : [];

        return redirect()->route('servidores.show', $servidor)->with('status', match (true) {
            $pendentes !== [] => 'Servidor criado. O IP informado aguarda liberação pelo suporte — assim que for liberado, o seu Unbound passa a receber a lista.',
            $liberados !== [] => 'Servidor criado e IP liberado: '.implode(', ', $liberados).'.',
            default => 'Servidor criado com sucesso.',
        });
    }

    public function show(Servidor $servidor): View
    {
        $this->authorizeAccess($servidor);

        $servidor->load(['empresa', 'listas', 'allowedIps']);

        $listasDisponiveis = Lista::where('status', 'active')
            ->where(function ($query) use ($servidor) {
                $query->whereNull('empresa_id')->orWhere('empresa_id', $servidor->empresa_id);
            })
            ->whereNotIn('id', $servidor->listas->pluck('id'))
            ->orderBy('nome')
            ->get();

        $logServidor = $this->logServidor($servidor);

        return view('servidores.show', compact('servidor', 'listasDisponiveis', 'logServidor'));
    }

    public function edit(Servidor $servidor): View
    {
        $this->authorizeAccess($servidor);

        $user = Auth::user();
        $empresas = $user->isAdmin() ? Empresa::orderBy('nome')->get() : collect([$user->empresa])->filter();
        $servidor->load('listas');
        $listasDisponiveis = $this->listasParaEmpresa($servidor->empresa_id);

        return view('servidores.form', compact('servidor', 'empresas', 'listasDisponiveis'));
    }

    public function update(Request $request, Servidor $servidor, TelegramNotifier $telegram, RpzDirtyMarker $rpzDirty): RedirectResponse
    {
        $this->authorizeAccess($servidor);

        $user = Auth::user();
        $data = $this->validated($request, $user);
        if ($data['tipo_dns'] === 'mikrotik') {
            $data['bloqueio_modo'] = 'nxdomain';
        }
        $listaIds = $data['lista_ids'] ?? [];
        unset($data['lista_ids']);

        if ($user->isCliente()) {
            $data['empresa_id'] = $user->empresa_id;
        }

        $empresaAnteriorId = $servidor->empresa_id;
        $servidor->update($data);
        $servidor->listas()->sync($listaIds);
        $rpzDirty->markForServer($servidor->id);
        if ($empresaAnteriorId !== $servidor->empresa_id) {
            $rpzDirty->markCompany($empresaAnteriorId);
        }

        AuditLog::record('servidor.updated', "Servidor \"{$servidor->nome}\" atualizado", $servidor->empresa_id, 'servidor', $servidor->id);

        if ($user->isCliente()) {
            $this->registrarIpsPendentes($servidor, $telegram);
        }

        return redirect()->route('servidores.show', $servidor)->with('status', 'Servidor atualizado com sucesso.');
    }

    public function destroy(Servidor $servidor, RpzDirtyMarker $rpzDirty): RedirectResponse
    {
        $this->authorizeAccess($servidor);

        AuditLog::record('servidor.destroyed', "Servidor \"{$servidor->nome}\" removido", $servidor->empresa_id, 'servidor', $servidor->id);

        $empresaId = $servidor->empresa_id;
        $servidorId = $servidor->id;
        $servidor->delete();
        $rpzDirty->markServer($servidorId);
        $rpzDirty->markCompany($empresaId);

        return redirect()->route('servidores.index')->with('status', 'Servidor removido.');
    }

    public function attachLista(Servidor $servidor, Lista $lista, RpzDirtyMarker $rpzDirty): RedirectResponse
    {
        $this->authorizeAccess($servidor);

        if ($lista->empresa_id !== null && $lista->empresa_id !== $servidor->empresa_id) {
            abort(403, 'Esta lista não está disponível para esta empresa.');
        }

        $servidor->listas()->syncWithoutDetaching([$lista->id]);
        $rpzDirty->markForServer($servidor->id);

        return redirect(route('servidores.show', $servidor).'#endpoint-lists')->with('status', "Lista \"{$lista->nome}\" adicionada ao servidor.");
    }

    public function detachLista(Servidor $servidor, Lista $lista, RpzDirtyMarker $rpzDirty): RedirectResponse
    {
        $this->authorizeAccess($servidor);

        $servidor->listas()->detach($lista->id);
        $rpzDirty->markForServer($servidor->id);

        return redirect(route('servidores.show', $servidor).'#endpoint-lists')->with('status', "Lista \"{$lista->nome}\" removida do servidor.");
    }

    public function unboundSeguranca(): View
    {
        return view('servidores.unbound-seguranca');
    }

    public function toggleIpRestriction(Servidor $servidor): RedirectResponse
    {
        $servidor->update(['ip_restriction_enabled' => ! $servidor->ip_restriction_enabled]);

        AuditLog::record(
            $servidor->ip_restriction_enabled ? 'servidor.ip_restriction_enabled' : 'servidor.ip_restriction_disabled',
            "Restrição de IP do servidor \"{$servidor->nome}\" ".($servidor->ip_restriction_enabled ? 'ativada' : 'desativada'),
            $servidor->empresa_id,
            'servidor',
            $servidor->id
        );

        return redirect(route('servidores.show', $servidor).'#endpoint-ips')->with('status', $servidor->ip_restriction_enabled
            ? 'Restrição de IP ativada.'
            : 'Restrição de IP desativada — qualquer IP com o token válido pode sincronizar.');
    }

    public function addAllowedIp(Request $request, Servidor $servidor): RedirectResponse
    {
        $data = $request->validate([
            'ip_cidr' => ['required', 'string', 'max:64'],
        ]);

        $value = trim($data['ip_cidr']);

        if (! Servidor::validIpOrCidr($value)) {
            return redirect(route('servidores.show', $servidor).'#endpoint-ips')->withErrors(['ip_cidr' => 'IP ou CIDR inválido.']);
        }

        $ip = $servidor->allowedIps()->firstOrCreate(['ip_cidr' => $value], ['status' => 'active']);
        if ($ip->status !== 'active') {
            $ip->update(['status' => 'active']);
        }

        AuditLog::record('servidor.ips.added', "IP {$value} adicionado ao servidor \"{$servidor->nome}\"", $servidor->empresa_id, 'servidor', $servidor->id);
        AuditLog::record('rpz.endpoint.acl_updated', 'ACL do endpoint RPZ atualizada', $servidor->empresa_id, 'servidor', $servidor->id);

        return redirect(route('servidores.show', $servidor).'#endpoint-ips')->with('status', 'IP adicionado à lista de permitidos.');
    }

    /**
     * Admin que cadastra o servidor já libera o IP informado (ACL ativa),
     * sem precisar voltar na tela pra adicionar. Só na criação: na edição
     * IPv4/IPv6 continuam informativos, pra editar um nome não liberar IP
     * que estava de fora de propósito.
     *
     * @return list<string> IPs liberados
     */
    private function liberarIpsInformados(Servidor $servidor): array
    {
        $liberados = [];

        foreach (array_unique(array_filter([trim((string) $servidor->ip_v4), trim((string) $servidor->ip_v6)])) as $ip) {
            $registro = $servidor->allowedIps()->firstOrCreate(['ip_cidr' => $ip], ['status' => 'active']);
            if ($registro->wasRecentlyCreated) {
                $liberados[] = $ip;
                AuditLog::record('servidor.ips.added', "IP {$ip} adicionado ao servidor \"{$servidor->nome}\"", $servidor->empresa_id, 'servidor', $servidor->id);
            }
        }

        return $liberados;
    }

    public function approveAllowedIp(Servidor $servidor, ServerAllowedIp $ip): RedirectResponse
    {
        if ($ip->servidor_id !== $servidor->id) {
            abort(404);
        }

        if ($ip->status !== 'active') {
            $ip->update(['status' => 'active']);
            AuditLog::record('servidor.ips.approved', "IP {$ip->ip_cidr} liberado para o servidor \"{$servidor->nome}\"", $servidor->empresa_id, 'servidor', $servidor->id);
        }

        return redirect(route('servidores.show', $servidor).'#endpoint-ips')->with('status', "IP {$ip->ip_cidr} liberado. O Unbound do cliente passa a receber a zona na próxima tentativa.");
    }

    /**
     * IPv4/IPv6 informados pelo cliente viram pedidos de liberação
     * (status "pending"): só o admin ativa. Pendentes que não batem mais com
     * o formulário são descartados; IPs já ativos nunca são tocados.
     *
     * @return list<string> IPs recém-registrados como pendentes
     */
    private function registrarIpsPendentes(Servidor $servidor, TelegramNotifier $telegram): array
    {
        $desejados = collect([$servidor->ip_v4, $servidor->ip_v6])
            ->map(fn ($ip) => is_string($ip) ? trim($ip) : '')
            ->filter()
            ->unique()
            ->values();

        $servidor->allowedIps()->where('status', 'pending')->whereNotIn('ip_cidr', $desejados)->delete();

        $existentes = $servidor->allowedIps()->pluck('ip_cidr');
        $novos = $desejados->reject(fn ($ip) => $existentes->contains($ip))->values();

        foreach ($novos as $ip) {
            $servidor->allowedIps()->create(['ip_cidr' => $ip, 'status' => 'pending']);
            AuditLog::record('servidor.ip_pendente', "IP {$ip} informado para o servidor \"{$servidor->nome}\", aguardando liberação", $servidor->empresa_id, 'servidor', $servidor->id);
        }

        if ($novos->isNotEmpty()) {
            $telegram->notifyIpAguardandoLiberacao(
                $servidor->empresa->nome ?? '-',
                $servidor->nome,
                $novos->all(),
                route('servidores.show', $servidor),
            );
        }

        return $novos->all();
    }

    public function removeAllowedIp(Servidor $servidor, ServerAllowedIp $ip): RedirectResponse
    {
        if ($ip->servidor_id !== $servidor->id) {
            abort(404);
        }

        $cidr = $ip->ip_cidr;
        $ip->delete();

        AuditLog::record('servidor.ips.removed', "IP {$cidr} removido do servidor \"{$servidor->nome}\"", $servidor->empresa_id, 'servidor', $servidor->id);
        AuditLog::record('rpz.endpoint.acl_updated', 'ACL do endpoint RPZ atualizada', $servidor->empresa_id, 'servidor', $servidor->id);

        return redirect(route('servidores.show', $servidor).'#endpoint-ips')->with('status', 'IP removido da lista de permitidos.');
    }

    /**
     * Log unico do servidor (ultimos 30 dias), no mesmo espirito da pagina
     * de Auditoria global mas escopado a este servidor: uma linha por
     * evento, ordenado por data, misturando dois tipos --
     *   - "sync": o Unbound do cliente veio buscar a zona (server_sync_logs)
     *   - "lista": uma lista vinculada a este servidor ganhou/perdeu dominios
     * Junto, da pra ver a causa e o efeito na mesma tabela: a lista muda
     * num dia, e a proxima sincronizacao depois disso ja reflete a mudanca
     * na contagem de dominios da proxima requisicao RPZ registrada.
     *
     * @return array<int, array{timestamp: Carbon, tipo: string, detalhe: string, meta: string|null, lista_id: int|null}>
     */
    private function logServidor(Servidor $servidor): array
    {
        $desde = now()->subDays(30)->startOfDay();
        if ($servidor->created_at && $servidor->created_at->greaterThan($desde)) {
            $desde = $servidor->created_at->copy();
        }
        $eventos = [];

        foreach ($servidor->syncLogs()->where('created_at', '>=', $desde)->orderByDesc('id')->limit(200)->get() as $log) {
            $eventos[] = [
                'timestamp' => $log->created_at,
                'tipo' => 'sync',
                // "Solicitou" (nao "sincronizou"/"aplicou"): o painel so observa a
                // requisicao HTTP atendida, nao a aplicacao real no Unbound do
                // cliente -- ver auditoria RPZ de 29/09/2026, secao 9.
                'detalhe' => "RPZ solicitada — {$log->dominios_count} domínio(s)",
                'meta' => $log->ip_address,
                'lista_id' => null,
            ];
        }

        // "Linha do tempo de entrega": alem de "o que mudou" (lista_add/remove)
        // e "quando o servidor veio buscar" (sync), mostra tambem "quando o
        // painel publicou" e "se o aviso (NOTIFY) chegou" -- os tres juntos
        // respondem "essa mudanca realmente chegou nesse servidor?" sem
        // precisar ir na Auditoria geral (RPZ-UNBOUND-LAB-1, 30/09/2026).
        $zonaRpz = RpzZone::where('zone_key', 'server:'.$servidor->id)->first();
        if ($zonaRpz !== null) {
            foreach ($zonaRpz->versions()->whereNotNull('published_at')->where('published_at', '>=', $desde)->orderByDesc('id')->limit(50)->get() as $versao) {
                $eventos[] = [
                    'timestamp' => $versao->published_at,
                    'tipo' => 'publish',
                    'detalhe' => "Zona publicada — revisão {$versao->revision}, serial {$versao->serial}",
                    'meta' => null,
                    'lista_id' => null,
                ];
            }
        }

        foreach (AuditLog::where('target_type', 'servidor')->where('target_id', $servidor->id)
            ->where('action', 'like', 'rpz.notify.%')->where('created_at', '>=', $desde)
            ->orderByDesc('id')->limit(50)->get() as $log) {
            $eventos[] = [
                'timestamp' => $log->created_at,
                'tipo' => $log->action === 'rpz.notify.acknowledged' ? 'notify_ok' : 'notify_falhou',
                'detalhe' => $log->description,
                'meta' => null,
                'lista_id' => null,
            ];
        }

        $listas = $servidor->listas->keyBy('id');
        $listaIds = $listas->keys();

        if ($listaIds->isNotEmpty()) {
            $adicionadosPorDiaLista = DB::table('dominios')
                ->join('lista_servidor', function ($join) use ($servidor) {
                    $join->on('lista_servidor.lista_id', '=', 'dominios.lista_id')
                        ->where('lista_servidor.servidor_id', '=', $servidor->id);
                })
                ->selectRaw('dominios.lista_id as lista_id, DATE(dominios.created_at) as dia, COUNT(*) as total')
                ->whereIn('dominios.lista_id', $listaIds)
                ->where('dominios.created_at', '>=', $desde)
                ->whereColumn('dominios.created_at', '>=', 'lista_servidor.created_at')
                ->groupBy('dominios.lista_id', 'dia')
                ->get();

            $removidosPorDiaLista = DB::table('dominios')
                ->join('lista_servidor', function ($join) use ($servidor) {
                    $join->on('lista_servidor.lista_id', '=', 'dominios.lista_id')
                        ->where('lista_servidor.servidor_id', '=', $servidor->id);
                })
                ->selectRaw('dominios.lista_id as lista_id, DATE(dominios.updated_at) as dia, COUNT(*) as total')
                ->whereIn('dominios.lista_id', $listaIds)
                ->where('dominios.ativo', false)
                ->where('dominios.updated_at', '>=', $desde)
                ->whereColumn('dominios.updated_at', '>=', 'lista_servidor.created_at')
                ->groupBy('dominios.lista_id', 'dia')
                ->get();

            foreach ($adicionadosPorDiaLista as $row) {
                $eventos[] = [
                    'timestamp' => Carbon::parse($row->dia)->endOfDay(),
                    'tipo' => 'lista_add',
                    'detalhe' => "Lista \"{$listas[$row->lista_id]->nome}\" ganhou {$row->total} domínio(s)",
                    'meta' => null,
                    'lista_id' => $row->lista_id,
                ];
            }

            foreach ($removidosPorDiaLista as $row) {
                $eventos[] = [
                    'timestamp' => Carbon::parse($row->dia)->endOfDay(),
                    'tipo' => 'lista_remove',
                    'detalhe' => "Lista \"{$listas[$row->lista_id]->nome}\" perdeu {$row->total} domínio(s)",
                    'meta' => null,
                    'lista_id' => $row->lista_id,
                ];
            }
        }

        usort($eventos, fn ($a, $b) => $b['timestamp'] <=> $a['timestamp']);

        return array_slice($eventos, 0, 80);
    }

    private function listasParaEmpresa(?int $empresaId)
    {
        return Lista::where('status', 'active')
            ->where(function ($query) use ($empresaId) {
                $query->whereNull('empresa_id')->orWhere('empresa_id', $empresaId);
            })
            ->orderBy('nome')
            ->get();
    }

    private function authorizeAccess(Servidor $servidor): void
    {
        $user = Auth::user();

        if ($user->isCliente() && $servidor->empresa_id !== $user->empresa_id) {
            abort(403);
        }
    }

    private function licencaBlocker(?Empresa $empresa): ?string
    {
        if (! $empresa) {
            return 'Sua empresa não está configurada corretamente. Fale com o administrador.';
        }

        $capacidade = $empresa->licencas()->valid()->sum('max_servidores');

        if ($capacidade === 0) {
            return 'Sua empresa não possui licença ativa. Fale com o administrador para liberar o cadastro de servidores.';
        }

        $atual = $empresa->servidores()->count();

        if ($atual >= $capacidade) {
            return "Limite de servidores da licença atingido ({$atual}/{$capacidade}). Fale com o administrador para ampliar.";
        }

        return null;
    }

    private function validated(Request $request, $user, bool $exigirIp = false): array
    {
        $empresaId = $user->isAdmin() ? $request->input('empresa_id') : $user->empresa_id;

        $rules = [
            'nome' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:active,inactive'],
            'tipo_dns' => ['required', 'in:unbound,mikrotik,bind9,outro'],
            'bloqueio_modo' => ['required', 'in:nxdomain,redirect'],
            'ip_v4' => $exigirIp ? ['required_without:ip_v6', 'nullable', 'ip'] : ['nullable', 'ip'],
            'ip_v6' => ['nullable', 'ip'],
            'lista_ids' => ['nullable', 'array'],
            'lista_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('listas', 'id')->where(function ($query) use ($empresaId) {
                    $query->where('status', 'active')
                        ->where(function ($scope) use ($empresaId) {
                            $scope->whereNull('empresa_id')->orWhere('empresa_id', $empresaId);
                        });
                }),
            ],
        ];

        if ($user->isAdmin()) {
            $rules['empresa_id'] = ['required', 'exists:empresas,id'];
        }

        return $request->validate($rules, [
            'ip_v4.required_without' => 'Informe o IP de saída do seu servidor DNS (IPv4 ou IPv6).',
        ]);
    }
}
