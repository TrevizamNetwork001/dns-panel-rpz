<?php

namespace App\Http\Controllers;

use App\Models\Dominio;
use App\Models\Empresa;
use App\Models\Lista;
use App\Models\Servidor;
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
        $totalServidores = Servidor::where('status', 'active')->count();
        $totalListas = Lista::where('status', 'active')->count();
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

        return view('dashboard.index', compact(
            'totalEmpresas',
            'totalServidores',
            'totalListas',
            'totalDominios',
            'totalDominiosAtivos',
            'listas',
            'servidores',
        ));
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

        $capacidadeLicenca = $empresa
            ? $empresa->licencas()
                ->where('status', 'active')
                ->whereDate('starts_at', '<=', now())
                ->where(function ($q) {
                    $q->whereNull('expires_at')->orWhereDate('expires_at', '>=', now());
                })
                ->sum('max_servidores')
            : 0;

        return view('dashboard.cliente', compact(
            'empresa',
            'totalServidores',
            'totalListas',
            'totalDominios',
            'totalDominiosAtivos',
            'listas',
            'servidores',
            'capacidadeLicenca',
        ));
    }
}
