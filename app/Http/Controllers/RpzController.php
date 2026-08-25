<?php

namespace App\Http\Controllers;

use App\Models\ServerSyncLog;
use App\Models\Servidor;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RpzController extends Controller
{
    /**
     * Gera o zonefile RPZ do servidor identificado pelo token.
     */
    public function show(Request $request, string $token): Response
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
        $dominios = DB::table('dominios')
            ->join('lista_servidor', 'lista_servidor.lista_id', '=', 'dominios.lista_id')
            ->join('listas', 'listas.id', '=', 'lista_servidor.lista_id')
            ->where('lista_servidor.servidor_id', $servidor->id)
            ->where('listas.status', 'active')
            ->where('dominios.ativo', true)
            ->distinct()
            ->orderBy('dominios.dominio')
            ->pluck('dominios.dominio');

        ServerSyncLog::create([
            'servidor_id' => $servidor->id,
            'ip_address' => $request->ip(),
            'dominios_count' => $dominios->count(),
            'created_at' => now(),
        ]);

        $zone = $this->buildZonefile($servidor, $dominios->all());

        return response($zone, 200)
            ->header('Content-Type', 'text/dns; charset=utf-8');
    }

    private function buildZonefile(Servidor $servidor, array $dominios): string
    {
        $serial = (string) now()->timestamp;
        $panelHost = parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost';
        $canario = 'blocktest.' . $panelHost;

        $target = $servidor->bloqueio_modo === 'redirect'
            ? rtrim($panelHost, '.') . '.'
            : '.';

        $mname = $panelHost . '.';
        $rname = 'hostmaster.' . $panelHost . '.';

        $lines = [];
        $lines[] = '$TTL 60';
        $lines[] = '@ SOA ' . $mname . ' ' . $rname . ' (';
        $lines[] = '    ' . $serial . '  ; serial';
        $lines[] = '    3600           ; refresh';
        $lines[] = '    600            ; retry';
        $lines[] = '    86400          ; expire';
        $lines[] = '    60 )           ; minimum';
        $lines[] = '  NS localhost.';
        $lines[] = '';
        $lines[] = '; dominio canario -- sempre presente, usado para testar a sincronizacao';
        $lines[] = $canario . ' CNAME .';
        $lines[] = '';

        foreach ($dominios as $dominio) {
            $lines[] = $dominio . ' CNAME ' . $target;
            $lines[] = '*.' . $dominio . ' CNAME ' . $target;
        }

        return implode("\n", $lines) . "\n";
    }
}
