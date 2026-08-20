<?php

namespace App\Http\Controllers;

use App\Models\Servidor;
use Illuminate\Http\Response;

class RpzController extends Controller
{
    /**
     * Gera o zonefile RPZ do servidor identificado pelo token.
     *
     * Esqueleto inicial: monta cabecalho SOA + dominio canario +
     * dominios ativos das listas vinculadas ao servidor. Sem
     * cache/otimizacao de serial ainda -- sera refinado depois.
     */
    public function show(string $token): Response
    {
        $servidor = Servidor::where('token', $token)
            ->where('status', 'active')
            ->whereHas('empresa', function ($query) {
                $query->where('status', 'active');
            })
            ->firstOrFail();

        $servidor->forceFill(['last_synced_at' => now()])->saveQuietly();

        $dominios = $servidor->listas()
            ->where('listas.status', 'active')
            ->with(['dominios' => function ($query) {
                $query->where('ativo', true);
            }])
            ->get()
            ->pluck('dominios')
            ->flatten()
            ->pluck('dominio')
            ->unique()
            ->sort()
            ->values();

        $zone = $this->buildZonefile($servidor, $dominios->all());

        return response($zone, 200)
            ->header('Content-Type', 'text/dns; charset=utf-8');
    }

    private function buildZonefile(Servidor $servidor, array $dominios): string
    {
        $serial = (string) now()->timestamp;
        $panelHost = parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost';
        $canario = 'blocktest.' . $panelHost;

        $lines = [];
        $lines[] = '$TTL 60';
        $lines[] = '@ SOA localhost. root.localhost. (';
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
            $lines[] = $dominio . ' CNAME .';
        }

        return implode("\n", $lines) . "\n";
    }
}
