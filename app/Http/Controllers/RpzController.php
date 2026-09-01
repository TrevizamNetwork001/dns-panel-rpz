<?php

namespace App\Http\Controllers;

use App\Models\ServerSyncLog;
use App\Models\Servidor;
use App\Services\RpzZoneBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RpzController extends Controller
{
    /**
     * Gera o zonefile RPZ do servidor identificado pelo token.
     */
    public function show(Request $request, string $token, RpzZoneBuilder $builder): Response
    {
        $servidor = Servidor::where('token', $token)
            ->where('status', 'active')
            ->whereHas('empresa', function ($query) {
                $query->where('status', 'active');
            })
            ->with('empresa')
            ->firstOrFail();

        if (! $servidor->ipAllowed($request->ip())) {
            throw new NotFoundHttpException();
        }

        if (! $servidor->empresa->possuiLicencaAtiva()) {
            throw new NotFoundHttpException();
        }

        $servidor->forceFill(['last_synced_at' => now()])->saveQuietly();

        // Consulta enxuta de proposito: com listas grandes (feeds de threat intel
        // chegam a dezenas de milhares de dominios), carregar tudo como models
        // Eloquent (via with()/pluck()/flatten() em memoria) estoura o
        // memory_limit do PHP-FPM. Aqui so trafega a coluna que interessa.
        $dominios = $builder->serverQuery($servidor->id);

        ServerSyncLog::create([
            'servidor_id' => $servidor->id,
            'ip_address' => $request->ip(),
            'dominios_count' => DB::query()->fromSub(clone $dominios, 'rpz_domains')->count(),
            'created_at' => now(),
        ]);

        $zone = $builder->build($dominios, $builder->target($servidor->bloqueio_modo));

        return response($zone, 200)
            ->header('Content-Type', 'text/dns; charset=utf-8');
    }

}
