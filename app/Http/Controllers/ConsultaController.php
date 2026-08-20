<?php

namespace App\Http\Controllers;

use App\Models\Dominio;
use App\Models\Lista;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ConsultaController extends Controller
{
    public function index(Request $request): View
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

        return view('consulta.index', compact('termoOriginal', 'termo', 'resultados', 'sugestoes'));
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
                    'lista' => $d->lista,
                    'dominio_cadastrado' => $d->dominio,
                    'tipo' => $d->dominio === $dominio ? 'exato' : 'subdominio (via wildcard)',
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
