<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Services\Fail2banStatusReader;
use Illuminate\View\View;

class SegurancaController extends Controller
{
    public function index(): View
    {
        $bansAtivos = DB::table('security_bans as bans')
            ->where('action', 'ban')
            ->whereNotExists(fn ($query) => $query->selectRaw('1')
                ->from('security_bans as newer')
                ->whereColumn('newer.jail', 'bans.jail')
                ->whereColumn('newer.ip_address', 'bans.ip_address')
                ->whereColumn('newer.id', '>', 'bans.id'))
            ->orderByDesc('created_at')->get();
        $snapshot = app(Fail2banStatusReader::class)->read();
        $estadoConfirmado = $snapshot !== null;
        if ($snapshot !== null) {
            $recorded = $bansAtivos->keyBy(fn ($ban) => $ban->jail.'|'.$ban->ip_address);
            $bansAtivos = collect($snapshot['bans'])->map(fn ($ban) => $recorded->get($ban['jail'].'|'.$ban['ip_address'])
                ?? (object) ['jail' => $ban['jail'], 'ip_address' => $ban['ip_address'], 'created_at' => null]);
        }
        $historico = DB::table('security_bans')->orderByDesc('id')->limit(50)->get();
        $bansUltimas24h = DB::table('security_bans')->where('action', 'ban')
            ->where('created_at', '>=', now()->subDay())->count();

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
            ->where('created_at', '>=', now()->subDays(7))
            ->orderByDesc('id')
            ->limit(15)
            ->get();

        return view('seguranca.index', compact(
            'bansAtivos',
            'estadoConfirmado',
            'historico',
            'bansUltimas24h',
            'loginFalhasUltimas24h',
            'ultimasFalhasLogin',
            'ultimoHealthCheck',
            'alertasSaude'
        ));
    }
}
