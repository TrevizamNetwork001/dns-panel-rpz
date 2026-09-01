<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SegurancaController extends Controller
{
    public function index(): View
    {
        $eventos = DB::table('security_bans')->orderByDesc('id')->limit(200)->get();

        $ativos = [];
        foreach ($eventos->sortBy('id') as $evento) {
            if ($evento->action === 'ban') {
                $ativos[$evento->ip_address] = $evento;
            } else {
                unset($ativos[$evento->ip_address]);
            }
        }
        $bansAtivos = collect($ativos)->sortByDesc('created_at')->values();

        $historico = $eventos->take(50);

        $bansUltimas24h = $eventos->filter(fn ($e) => Carbon::parse($e->created_at)->gte(now()->subHours(24)) && $e->action === 'ban')->count();

        $loginFalhasUltimas24h = AuditLog::where('action', 'auth.login_failed')
            ->where('created_at', '>=', now()->subHours(24))
            ->count();

        $ultimasFalhasLogin = AuditLog::where('action', 'auth.login_failed')
            ->orderByDesc('id')
            ->limit(15)
            ->get();

        $ultimoHealthCheck = AuditLog::where('action', 'like', 'health.%')
            ->orderByDesc('id')
            ->first();

        $alertasSaude = AuditLog::where('action', 'like', 'health.%')
            ->where('action', '!=', 'health.ok')
            ->orderByDesc('id')
            ->limit(15)
            ->get();

        return view('seguranca.index', compact(
            'bansAtivos',
            'historico',
            'bansUltimas24h',
            'loginFalhasUltimas24h',
            'ultimasFalhasLogin',
            'ultimoHealthCheck',
            'alertasSaude'
        ));
    }
}
