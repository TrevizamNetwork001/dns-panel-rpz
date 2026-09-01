<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Empresa;
use App\Models\ServerSyncLog;
use App\Models\Servidor;
use App\Services\RpzZoneBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RpzController extends Controller
{
    /**
     * Gera o zonefile RPZ do servidor identificado pelo token.
     */
    public function show(Request $request, string $identifier, RpzZoneBuilder $builder): Response|StreamedResponse
    {
        $empresa = Empresa::where('rpz_slug', $identifier)->where('status', 'active')->first();
        if ($empresa !== null) {
            return $this->showCompany($request, $empresa, $builder);
        }

        $servidor = Servidor::where('token', $identifier)
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

    private function showCompany(Request $request, Empresa $empresa, RpzZoneBuilder $builder): Response|StreamedResponse
    {
        if (! $empresa->possuiLicencaAtiva()) {
            throw new NotFoundHttpException();
        }

        $ip = (string) $request->ip();
        $matchingServers = Servidor::query()
            ->where('empresa_id', $empresa->id)
            ->where('status', 'active')
            ->whereHas('allowedIps', fn ($query) => $query->where('status', 'active'))
            ->with(['allowedIps' => fn ($query) => $query->where('status', 'active')])
            ->get()
            ->filter(fn (Servidor $servidor): bool => $servidor->allowedIps
                ->contains(fn ($rule): bool => Servidor::ipMatchesCidr($ip, $rule->ip_cidr)));

        if ($matchingServers->isEmpty()) {
            AuditLog::record('rpz.endpoint.denied', 'Acesso ao endpoint RPZ empresarial negado', $empresa->id, 'empresa', $empresa->id);
            return response()->view('rpz.access-denied', [], 403, [
                'Cache-Control' => 'private, no-store, max-age=0',
                'Content-Type' => 'text/html; charset=UTF-8',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        $domains = $builder->companyQuery($empresa->id);
        $count = DB::query()->fromSub(clone $domains, 'rpz_domains')->count();

        foreach ($matchingServers as $servidor) {
            $servidor->forceFill(['last_synced_at' => now()])->saveQuietly();
            ServerSyncLog::create([
                'servidor_id' => $servidor->id,
                'ip_address' => $ip,
                'dominios_count' => $count,
                'created_at' => now(),
            ]);
        }

        AuditLog::record('rpz.endpoint.downloaded', "Endpoint RPZ empresarial baixado ({$count} domínios)", $empresa->id, 'empresa', $empresa->id);
        $serial = (string) now()->timestamp;

        return response()->stream(function () use ($builder, $domains, $serial): void {
            foreach ($builder->lines($domains, '.', $serial) as $line) {
                echo $line."\n";
            }
        }, 200, [
            'Content-Type' => 'text/dns; charset=utf-8',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

}
