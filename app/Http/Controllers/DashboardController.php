<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Dominio;
use App\Models\Empresa;
use App\Models\Lista;
use App\Models\ServerSyncLog;
use App\Models\Servidor;
use App\Models\SugestaoDominio;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();

        if ($user->isCliente()) {
            return $this->clienteDashboard($user);
        }

        $totalEmpresas = Empresa::where('status', 'active')->count();
        $totalServidoresAtivos = Servidor::where('status', 'active')->count();
        $totalServidoresTotal = Servidor::count();
        $servidoresAtencao = Servidor::where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('last_synced_at')->orWhere('last_synced_at', '<=', now()->subDays(2));
            })
            ->count();
        $totalListasAtivas = Lista::where('status', 'active')->count();
        $totalListasTotal = Lista::count();
        $totalDominios = Dominio::count();
        $totalDominiosAtivos = Dominio::where('ativo', true)->count();

        $listas = Lista::with('empresa')
            ->withCount([
                'dominios',
                'dominios as dominios_ativos_count' => function ($query) {
                    $query->where('ativo', true);
                },
            ])
            ->orderByDesc('dominios_count')
            ->get();

        $servidores = Servidor::with('empresa')
            ->orderByRaw('last_synced_at IS NULL, last_synced_at DESC')
            ->take(10)
            ->get();

        $resumoEndpoints = [
            'normal' => Servidor::whereNotNull('last_synced_at')
                ->where('last_synced_at', '>', now()->subDays(2))
                ->count(),
            'atencao' => Servidor::whereNotNull('last_synced_at')
                ->where('last_synced_at', '<=', now()->subDays(2))
                ->count(),
            'sem_consulta' => Servidor::whereNull('last_synced_at')->count(),
        ];

        $totalEmpresasTotal = Empresa::count();

        $atividadeRecente = $this->atividadeRecente();

        return view('dashboard.index', compact(
            'totalEmpresas',
            'totalEmpresasTotal',
            'totalServidoresAtivos',
            'totalServidoresTotal',
            'servidoresAtencao',
            'totalListasAtivas',
            'totalListasTotal',
            'totalDominios',
            'totalDominiosAtivos',
            'listas',
            'servidores',
            'resumoEndpoints',
            'atividadeRecente',
        ));
    }

    /**
     * Formata um timestamp em portugues, com granularidade fina (min/h) para
     * atividade recente -- o app roda com APP_LOCALE=en, entao Carbon::diffForHumans()
     * sairia em ingles; por isso o texto e montado na mao aqui, no mesmo
     * espirito do "ha X dias" que ja existia no resto do painel.
     */
    public static function relativoPt(?Carbon $data): string
    {
        if ($data === null) {
            return 'nunca';
        }

        $segundos = (int) $data->diffInSeconds(now());

        if ($segundos < 60) {
            return 'agora mesmo';
        }

        if ($segundos < 3600) {
            $min = intdiv($segundos, 60);

            return "há {$min} min";
        }

        if ($segundos < 86400) {
            $horas = intdiv($segundos, 3600);

            return "há {$horas} h";
        }

        $dias = intdiv($segundos, 86400);

        return "há {$dias} ".($dias === 1 ? 'dia' : 'dias');
    }

    /**
     * Titulo curto + icone/cor pra um evento da auditoria no feed "Atividade
     * recente" -- mapeado por acao especifica (nao por categoria generica),
     * pra bater com o texto real de cada tipo de evento que o app registra.
     *
     * @return array{titulo: string, icone: string, cor: string}
     */
    private static function atividadeInfo(string $action): array
    {
        return match (true) {
            $action === 'auth.login' => ['titulo' => 'Login realizado', 'icone' => 'seguranca', 'cor' => 'muted'],
            $action === 'auth.login_failed' => ['titulo' => 'Falha de login', 'icone' => 'seguranca', 'cor' => 'danger'],
            $action === 'auth.logout' => ['titulo' => 'Logout', 'icone' => 'seguranca', 'cor' => 'muted'],
            $action === 'auth.password_changed' => ['titulo' => 'Senha alterada', 'icone' => 'seguranca', 'cor' => 'muted'],
            $action === 'configuracoes.telegram_atualizado' => ['titulo' => 'Configuração atualizada', 'icone' => 'sistema', 'cor' => 'muted'],
            $action === 'empresa.cadastro_publico' => ['titulo' => 'Empresa cadastrada', 'icone' => 'empresa', 'cor' => 'cyan'],
            $action === 'empresa.created' => ['titulo' => 'Empresa criada', 'icone' => 'empresa', 'cor' => 'cyan'],
            $action === 'empresa.destroyed' => ['titulo' => 'Empresa removida', 'icone' => 'empresa', 'cor' => 'danger'],
            $action === 'empresa.updated' => ['titulo' => 'Empresa atualizada', 'icone' => 'empresa', 'cor' => 'cyan'],
            str_starts_with($action, 'health.') => ['titulo' => 'Alerta de saúde do servidor', 'icone' => 'sistema', 'cor' => 'warning'],
            $action === 'licenca.created' => ['titulo' => 'Licença criada', 'icone' => 'licenca', 'cor' => 'amber'],
            $action === 'licenca.destroyed' => ['titulo' => 'Licença removida', 'icone' => 'licenca', 'cor' => 'danger'],
            $action === 'licenca.updated' => ['titulo' => 'Licença atualizada', 'icone' => 'licenca', 'cor' => 'amber'],
            $action === 'lista.created' => ['titulo' => 'Fonte criada', 'icone' => 'fonte', 'cor' => 'green'],
            $action === 'lista.destroyed' => ['titulo' => 'Fonte removida', 'icone' => 'fonte', 'cor' => 'danger'],
            $action === 'lista.updated' => ['titulo' => 'Fonte atualizada', 'icone' => 'fonte', 'cor' => 'cyan'],
            $action === 'lista.externa.sync' => ['titulo' => 'Fonte sincronizada', 'icone' => 'fonte', 'cor' => 'green'],
            $action === 'lista.externa.sync_falhou' => ['titulo' => 'Falha na sincronização', 'icone' => 'fonte', 'cor' => 'danger'],
            $action === 'lista.externa.sync_habilitado' => ['titulo' => 'Sincronização reativada', 'icone' => 'fonte', 'cor' => 'green'],
            $action === 'lista.externa.sync_pausado' => ['titulo' => 'Sincronização pausada', 'icone' => 'fonte', 'cor' => 'warning'],
            $action === 'servidor.created' => ['titulo' => 'Endpoint cadastrado', 'icone' => 'endpoint', 'cor' => 'violet'],
            $action === 'servidor.destroyed' => ['titulo' => 'Endpoint removido', 'icone' => 'endpoint', 'cor' => 'danger'],
            $action === 'servidor.updated' => ['titulo' => 'Endpoint atualizado', 'icone' => 'endpoint', 'cor' => 'violet'],
            $action === 'servidor.ips.added' => ['titulo' => 'IP liberado', 'icone' => 'seguranca', 'cor' => 'cyan'],
            $action === 'servidor.ips.removed' => ['titulo' => 'IP removido', 'icone' => 'seguranca', 'cor' => 'warning'],
            $action === 'servidor.ip_restriction_enabled' => ['titulo' => 'Restrição de IP ativada', 'icone' => 'seguranca', 'cor' => 'cyan'],
            $action === 'servidor.ip_restriction_disabled' => ['titulo' => 'Restrição de IP desativada', 'icone' => 'seguranca', 'cor' => 'warning'],
            $action === 'sugestao.approved' => ['titulo' => 'Sugestão aprovada', 'icone' => 'sugestao', 'cor' => 'amber'],
            $action === 'sugestao.created' => ['titulo' => 'Sugestão enviada', 'icone' => 'sugestao', 'cor' => 'amber'],
            $action === 'sugestao.rejected' => ['titulo' => 'Sugestão rejeitada', 'icone' => 'sugestao', 'cor' => 'danger'],
            $action === 'user.created' => ['titulo' => 'Usuário criado', 'icone' => 'usuario', 'cor' => 'violet'],
            $action === 'user.destroyed' => ['titulo' => 'Usuário removido', 'icone' => 'usuario', 'cor' => 'danger'],
            $action === 'user.password_reset' => ['titulo' => 'Senha redefinida', 'icone' => 'usuario', 'cor' => 'violet'],
            $action === 'user.updated' => ['titulo' => 'Usuário atualizado', 'icone' => 'usuario', 'cor' => 'violet'],
            default => ['titulo' => 'Auditoria', 'icone' => 'sistema', 'cor' => 'muted'],
        };
    }

    /**
     * "Atividade recente" mistura dois tipos de evento real: acoes da
     * auditoria (AuditLog) e consultas de endpoints ao RPZ (ServerSyncLog,
     * a mesma tabela ja usada na pagina de cada endpoint). Sao tabelas
     * separadas porque tem naturezas diferentes -- auditoria e "quem fez o
     * que", sync log e "quando o Unbound do cliente buscou a zona" -- entao
     * cada linha vira um array normalizado {tipo, timestamp, titulo, icone,
     * cor, descricao} pra a view nao precisar saber a origem.
     *
     * @return Collection<int, array{timestamp: Carbon, titulo: string, icone: string, cor: string, descricao: string}>
     */
    private function atividadeRecente()
    {
        $auditoria = AuditLog::where('action', '!=', 'health.ok')
            ->orderByDesc('id')
            ->take(8)
            ->get()
            ->map(function (AuditLog $log) {
                $info = self::atividadeInfo($log->action);

                return [
                    'timestamp' => $log->created_at,
                    'titulo' => $info['titulo'],
                    'icone' => $info['icone'],
                    'cor' => $info['cor'],
                    'descricao' => $log->description ?? '',
                ];
            });

        $sincronizacoes = ServerSyncLog::with('servidor:id,nome')
            ->orderByDesc('id')
            ->take(8)
            ->get()
            ->map(function (ServerSyncLog $log) {
                $nome = $log->servidor->nome ?? "endpoint #{$log->servidor_id}";

                return [
                    'timestamp' => $log->created_at,
                    'titulo' => 'Endpoint consultou RPZ',
                    'icone' => 'endpoint',
                    'cor' => 'violet',
                    'descricao' => "{$nome} consultou o RPZ com sucesso (".number_format($log->dominios_count, 0, ',', '.').' domínios entregues)',
                ];
            });

        return $auditoria->concat($sincronizacoes)
            ->sortByDesc('timestamp')
            ->take(5)
            ->values();
    }

    private function clienteDashboard($user): View
    {
        $empresa = $user->empresa;

        $totalServidores = $empresa ? $empresa->servidores()->count() : 0;
        $totalListas = $empresa
            ? Lista::where(function ($q) use ($empresa) {
                $q->whereNull('empresa_id')->orWhere('empresa_id', $empresa->id);
            })->where('status', 'active')->count()
            : 0;

        $servidorIds = $empresa ? $empresa->servidores()->pluck('id') : collect();
        $totalDominios = $servidorIds->isEmpty() ? 0 : Dominio::whereHas('lista.servidores', function ($q) use ($servidorIds) {
            $q->whereIn('servidores.id', $servidorIds);
        })->count();
        $totalDominiosAtivos = $servidorIds->isEmpty() ? 0 : Dominio::where('ativo', true)->whereHas('lista.servidores', function ($q) use ($servidorIds) {
            $q->whereIn('servidores.id', $servidorIds);
        })->count();

        $listas = $empresa
            ? Lista::where(function ($q) use ($empresa) {
                $q->whereNull('empresa_id')->orWhere('empresa_id', $empresa->id);
            })
                ->withCount([
                    'dominios',
                    'dominios as dominios_ativos_count' => function ($query) {
                        $query->where('ativo', true);
                    },
                ])
                ->orderByDesc('dominios_count')
                ->get()
            : collect();

        $servidores = $empresa
            ? $empresa->servidores()->orderByRaw('last_synced_at IS NULL, last_synced_at DESC')->get()
            : collect();

        $licencas = $empresa
            ? $empresa->licencas()->orderByDesc('starts_at')->orderByDesc('id')->get()
            : collect();
        $capacidadeLicenca = $licencas->filter->isValid()->sum('max_servidores');
        $estadoLicenca = $capacidadeLicenca > 0
            ? null
            : ($licencas->first()?->unavailableSummary() ?? 'Sem licença ativa');

        $sugestoesRecentes = $empresa
            ? SugestaoDominio::where('empresa_id', $empresa->id)
                ->with('lista')
                ->orderByDesc('updated_at')
                ->take(5)
                ->get()
            : collect();

        return view('dashboard.cliente', compact(
            'empresa',
            'totalServidores',
            'totalListas',
            'totalDominios',
            'totalDominiosAtivos',
            'listas',
            'servidores',
            'capacidadeLicenca',
            'estadoLicenca',
            'sugestoesRecentes',
        ));
    }
}
