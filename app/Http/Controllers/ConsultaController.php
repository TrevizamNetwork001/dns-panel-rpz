<?php

namespace App\Http\Controllers;

use App\Models\Dominio;
use App\Models\Lista;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ConsultaController extends Controller
{
    public function index(Request $request): View
    {
        return view('consulta.index', $this->buscarResultados($request));
    }

    /**
     * Pagina "Dominios", central de operacao que incorpora a mesma busca da
     * antiga Consulta (rota consulta.index continua existindo por compatibilidade,
     * mas some do menu principal) e adiciona listagem paginada, filtros e
     * contadores sobre a base completa de dominios.
     */
    public function pagina(Request $request): View
    {
        $user = Auth::user();

        $termoOriginal = trim((string) $request->input('dominio', ''));
        $termo = $termoOriginal !== '' ? $this->normalizar($termoOriginal) : null;

        $resultadoExato = $termo !== null ? $this->buscarResultadoExato($termo) : null;

        $statusFiltro = in_array($request->query('status'), ['ativos', 'inativos'], true)
            ? $request->query('status')
            : null;

        $fontesDisponiveis = Lista::query()
            ->when($user->isCliente(), function ($q) use ($user) {
                $q->where(function ($sub) use ($user) {
                    $sub->whereNull('empresa_id')->orWhere('empresa_id', $user->empresa_id);
                });
            })
            ->orderBy('nome')
            ->get(['id', 'nome', 'origem']);

        $fonteId = $request->query('fonte_id');
        $fonteId = $fonteId && $fontesDisponiveis->contains('id', (int) $fonteId) ? (int) $fonteId : null;

        $tabela = $this->listarDominiosAgrupados($request, $user, $termoOriginal, $statusFiltro, $fonteId);

        $contadores = $this->contadoresGerais($user);

        return view('dominios.busca', [
            'termoOriginal' => $termoOriginal,
            'termo' => $termo,
            'resultadoExato' => $resultadoExato,
            'statusFiltro' => $statusFiltro,
            'fonteId' => $fonteId,
            'fontesDisponiveis' => $fontesDisponiveis,
            'dominios' => $tabela['paginacao'],
            'fontesPorDominio' => $tabela['fontesPorDominio'],
            'contadores' => $contadores,
        ]);
    }

    /**
     * Bloco de resultado exato exibido acima da tabela quando a busca bate
     * com um dominio (ou um dominio-pai, via wildcard) cadastrado e ativo.
     * Reaproveita a mesma logica de match usada pela antiga Consulta.
     *
     * @return array{termo: string, fontes: Collection, primeiraOcorrencia: ?Carbon, ultimaAtualizacao: ?Carbon}|null
     */
    private function buscarResultadoExato(string $termo): ?array
    {
        $matches = $this->buscar($termo);

        if ($matches->isEmpty()) {
            return null;
        }

        return [
            'termo' => $termo,
            'matches' => $matches,
            'fontes' => $matches->pluck('lista')->unique('id')->values(),
            'primeiraOcorrencia' => $matches->min('created_at'),
            'ultimaAtualizacao' => $matches->max('updated_at'),
        ];
    }

    /**
     * Listagem principal da tela: uma linha por dominio (agrupado entre
     * fontes), paginada e filtrada -- nunca carrega a base inteira em
     * memoria. Uma segunda query, limitada aos dominios da pagina atual,
     * busca o detalhe por fonte (evita N+1 sem trazer as ~250k linhas).
     */
    private function listarDominiosAgrupados(Request $request, $user, string $termoOriginal, ?string $statusFiltro, ?int $fonteId): array
    {
        $base = Dominio::query()
            ->join('listas', 'listas.id', '=', 'dominios.lista_id')
            ->when($user->isCliente(), function ($q) use ($user) {
                $q->where(function ($sub) use ($user) {
                    $sub->whereNull('listas.empresa_id')->orWhere('listas.empresa_id', $user->empresa_id);
                });
            })
            ->when($fonteId, fn ($q) => $q->where('dominios.lista_id', $fonteId))
            ->when($termoOriginal !== '', fn ($q) => $q->where('dominios.dominio', 'like', '%' . strtolower($termoOriginal) . '%'))
            ->when($statusFiltro === 'ativos', fn ($q) => $q->where('dominios.ativo', true))
            ->when($statusFiltro === 'inativos', fn ($q) => $q->where('dominios.ativo', false));

        $paginacao = (clone $base)
            ->selectRaw('dominios.dominio as dominio, MIN(dominios.created_at) as adicionado_em, MAX(dominios.updated_at) as atualizado_em, MAX(dominios.ativo) as algum_ativo, COUNT(DISTINCT dominios.lista_id) as fontes_count')
            ->groupBy('dominios.dominio')
            ->orderBy('dominios.dominio')
            ->simplePaginate(50)
            ->withQueryString();

        $dominiosDaPagina = collect($paginacao->items())->pluck('dominio');

        $fontesPorDominio = collect();

        if ($dominiosDaPagina->isNotEmpty()) {
            $fontesPorDominio = Dominio::query()
                ->select(['id', 'lista_id', 'dominio', 'ativo', 'created_at', 'updated_at'])
                ->whereIn('dominio', $dominiosDaPagina)
                ->when($user->isCliente(), function ($q) use ($user) {
                    $q->whereHas('lista', function ($sub) use ($user) {
                        $sub->whereNull('empresa_id')->orWhere('empresa_id', $user->empresa_id);
                    });
                })
                ->when($fonteId, fn ($q) => $q->where('lista_id', $fonteId))
                ->with('lista:id,nome,empresa_id,origem')
                ->get()
                ->groupBy('dominio');
        }

        return ['paginacao' => $paginacao, 'fontesPorDominio' => $fontesPorDominio];
    }

    /**
     * Contadores do topo (ativos/inativos/total). Sempre sobre a base
     * completa visivel ao usuario, independente dos filtros da tabela --
     * duas contagens agregadas, sem carregar nenhum model.
     */
    private function contadoresGerais($user): array
    {
        $query = Dominio::query()
            ->join('listas', 'listas.id', '=', 'dominios.lista_id')
            ->when($user->isCliente(), function ($q) use ($user) {
                $q->where(function ($sub) use ($user) {
                    $sub->whereNull('listas.empresa_id')->orWhere('listas.empresa_id', $user->empresa_id);
                });
            });

        $ativos = (clone $query)->where('dominios.ativo', true)->count();
        $total = (clone $query)->count();

        return [
            'ativos' => $ativos,
            'inativos' => $total - $ativos,
            'total' => $total,
        ];
    }

    /**
     * @return array{termoOriginal: string, termo: ?string, resultados: Collection, sugestoes: Collection}
     */
    private function buscarResultados(Request $request): array
    {
        $termoOriginal = trim((string) $request->input('dominio', ''));
        $resultados = collect();
        $sugestoes = collect();
        $termo = null;

        if ($termoOriginal !== '') {
            $termo = $this->normalizar($termoOriginal);
            $resultados = $this->buscar($termo);

            if ($resultados->isEmpty()) {
                $sugestoes = $this->buscarPorTrecho($termoOriginal);
            }
        }

        return compact('termoOriginal', 'termo', 'resultados', 'sugestoes');
    }

    private function normalizar(string $valor): string
    {
        $valor = strtolower(trim($valor));
        $valor = preg_replace('#^https?://#', '', $valor);
        $valor = explode('/', $valor)[0];
        $valor = rtrim($valor, '.');

        return $valor;
    }

    /**
     * Retorna as listas visiveis para o usuario que bloqueiam o dominio,
     * seja por match exato ou porque um dominio-pai esta cadastrado
     * (o zonefile RPZ gera regra wildcard *.dominio para cada entrada).
     */
    private function buscar(string $dominio)
    {
        $user = Auth::user();

        $candidatos = $this->sufixosPai($dominio);
        $candidatos[] = $dominio;
        $candidatos = array_unique($candidatos);

        $query = Dominio::whereIn('dominio', $candidatos)
            ->where('ativo', true)
            ->with(['lista' => function ($q) use ($user) {
                $q->with('empresa');
                if ($user->isCliente()) {
                    $q->where(function ($sub) use ($user) {
                        $sub->whereNull('empresa_id')->orWhere('empresa_id', $user->empresa_id);
                    });
                }
            }]);

        return $query->get()
            ->filter(fn (Dominio $d) => $d->lista !== null)
            ->map(function (Dominio $d) use ($dominio) {
                return [
                    'id' => $d->id,
                    'ativo' => $d->ativo,
                    'lista' => $d->lista,
                    'dominio_cadastrado' => $d->dominio,
                    'tipo' => $d->dominio === $dominio ? 'exato' : 'subdominio (via wildcard)',
                    'created_at' => $d->created_at,
                    'updated_at' => $d->updated_at,
                ];
            })
            ->values();
    }

    /**
     * Busca por trecho (substring), usada quando nao ha match exato/wildcard --
     * util quando o usuario digita um pedaco do dominio (ex: "abreviar" um nome).
     */
    private function buscarPorTrecho(string $termoOriginal)
    {
        $user = Auth::user();
        $trecho = strtolower(trim($termoOriginal));

        if ($trecho === '' || strlen($trecho) < 3) {
            return collect();
        }

        $query = Dominio::where('dominio', 'like', '%' . $trecho . '%')
            ->with(['lista' => function ($q) use ($user) {
                $q->with('empresa');
                if ($user->isCliente()) {
                    $q->where(function ($sub) use ($user) {
                        $sub->whereNull('empresa_id')->orWhere('empresa_id', $user->empresa_id);
                    });
                }
            }])
            ->limit(20);

        return $query->get()
            ->filter(fn (Dominio $d) => $d->lista !== null)
            ->values();
    }

    private function sufixosPai(string $dominio): array
    {
        $partes = explode('.', $dominio);
        $sufixos = [];

        for ($i = 1; $i < count($partes) - 1; $i++) {
            $sufixos[] = implode('.', array_slice($partes, $i));
        }

        return $sufixos;
    }
}
