<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class AuditoriaController extends Controller
{
    private const WINDOW = 1000;

    private const PER_PAGE = 50;

    public function index(Request $request): View
    {
        $query = trim((string) $request->input('q', ''));
        $bucketFilter = (string) $request->input('bucket', 'all');
        $categoryFilter = (string) $request->input('categoria', 'all');
        $userFilter = (string) $request->input('usuario', 'all');
        $dataInicio = (string) $request->input('data_inicio', '');
        $dataFim = (string) $request->input('data_fim', '');
        $page = max(1, (int) $request->input('page', 1));

        $builder = AuditLog::with(['user', 'empresa'])->orderByDesc('created_at')->orderByDesc('id');

        if ($categoryFilter !== 'all' && array_key_exists($categoryFilter, AuditLog::categories())) {
            $prefixes = AuditLog::prefixesForCategory($categoryFilter);
            $builder->where(function ($q) use ($prefixes) {
                foreach ($prefixes as $prefix) {
                    $q->orWhere('action', $prefix)->orWhere('action', 'like', $prefix.'.%');
                }
            });
        }

        if ($userFilter === 'sistema') {
            $builder->whereNull('user_id');
        } elseif ($userFilter !== 'all' && $userFilter !== '') {
            $builder->where('user_id', $userFilter);
        }

        if ($dataInicio !== '') {
            $builder->whereDate('created_at', '>=', $dataInicio);
        }

        if ($dataFim !== '') {
            $builder->whereDate('created_at', '<=', $dataFim);
        }

        if ($query !== '') {
            $builder->where(function ($q) use ($query) {
                $like = '%'.$query.'%';
                $q->where('action', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('ip_address', 'like', $like)
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $like))
                    ->orWhereHas('empresa', fn ($e) => $e->where('nome', 'like', $like));
            });
        }

        // A "severidade" e uma heuristica calculada em PHP sobre o texto da
        // action (AuditLog::bucket), nao um filtro traduzivel direto pra SQL
        // -- entao filtra/pagina sobre uma janela ja reduzida pelos filtros
        // de banco acima (categoria/usuario/periodo/busca), nao mais sobre
        // um corte fixo dos ultimos 200 registros do sistema inteiro.
        $logs = $builder->limit(self::WINDOW)->get();

        if ($bucketFilter !== 'all') {
            $logs = $logs->filter(fn (AuditLog $log) => AuditLog::bucket($log->action) === $bucketFilter)->values();
        }

        $totalFiltered = $logs->count();
        $visibleLogs = new LengthAwarePaginator(
            $logs->forPage($page, self::PER_PAGE)->values(),
            $totalFiltered,
            self::PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $recentCount = $logs->filter(fn (AuditLog $log) => $log->created_at->gte(now()->subHours(24)))->count();
        $authFailureCount = $logs->filter(fn (AuditLog $log) => str_contains($log->action, 'login_failed'))->count();
        $destructiveCount = $logs->filter(fn (AuditLog $log) => AuditLog::bucket($log->action) === 'warning')->count();

        // Busca os nomes em lote, sem uma consulta por evento.
        $targetNames = [];
        foreach (['servidor' => \App\Models\Servidor::class, 'lista' => \App\Models\Lista::class, 'empresa' => \App\Models\Empresa::class] as $type => $model) {
            $ids = $visibleLogs->getCollection()->where('target_type', $type)->pluck('target_id')->filter()->unique();
            $targetNames[$type] = $ids->isEmpty() ? collect() : $model::whereIn('id', $ids)->pluck('nome', 'id');
        }

        $users = User::orderBy('name')->get(['id', 'name']);

        return view('auditoria.index', compact(
            'visibleLogs', 'totalFiltered', 'recentCount', 'authFailureCount', 'destructiveCount',
            'query', 'bucketFilter', 'categoryFilter', 'userFilter', 'dataInicio', 'dataFim', 'users', 'targetNames'
        ));
    }
}
