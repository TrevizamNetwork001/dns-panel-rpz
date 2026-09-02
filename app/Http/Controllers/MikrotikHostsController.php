<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Empresa;
use App\Models\ServerSyncLog;
use App\Models\Servidor;
use App\Services\RpzZoneBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MikrotikHostsController extends Controller
{
    public function show(Request $request, string $identifier, RpzZoneBuilder $builder): Response|StreamedResponse
    {
        $empresa = Empresa::where('rpz_slug', $identifier)->where('status', 'active')->first();
        if ($empresa !== null) {
            return $this->showCompany($request, $empresa, $builder);
        }

        $servidor = Servidor::where('token', $identifier)
            ->where('status', 'active')
            ->whereHas('empresa', fn ($query) => $query->where('status', 'active'))
            ->with('empresa')
            ->firstOrFail();

        if (! $servidor->ipAllowed((string) $request->ip()) || ! $servidor->empresa->possuiLicencaAtiva()) {
            throw new NotFoundHttpException;
        }

        $domains = $builder->serverQuery($servidor->id);
        $count = DB::query()->fromSub(clone $domains, 'hosts_domains')->count();
        $this->recordSync($servidor, (string) $request->ip(), $count);

        return $this->stream($builder, $domains);
    }

    private function showCompany(Request $request, Empresa $empresa, RpzZoneBuilder $builder): Response|StreamedResponse
    {
        if (! $empresa->possuiLicencaAtiva()) {
            throw new NotFoundHttpException;
        }

        $ip = (string) $request->ip();
        $matchingServers = Servidor::query()
            ->where('empresa_id', $empresa->id)
            ->where('status', 'active')
            ->whereHas('allowedIps', fn ($query) => $query->where('status', 'active'))
            ->with(['allowedIps' => fn ($query) => $query->where('status', 'active')])
            ->get()
            ->filter(fn (Servidor $server): bool => $server->allowedIps
                ->contains(fn ($rule): bool => Servidor::ipMatchesCidr($ip, $rule->ip_cidr)));

        if ($matchingServers->isEmpty()) {
            AuditLog::record('mikrotik.endpoint.denied', 'Acesso ao feed MikroTik empresarial negado', $empresa->id, 'empresa', $empresa->id);

            return response()->view('rpz.access-denied', [], 403, [
                'Cache-Control' => 'private, no-store, max-age=0',
                'Content-Type' => 'text/html; charset=UTF-8',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        $domains = $builder->companyQuery($empresa->id);
        $count = DB::query()->fromSub(clone $domains, 'hosts_domains')->count();
        foreach ($matchingServers as $servidor) {
            $this->recordSync($servidor, $ip, $count);
        }
        AuditLog::record('mikrotik.endpoint.downloaded', "Feed MikroTik empresarial baixado ({$count} domínios)", $empresa->id, 'empresa', $empresa->id);

        return $this->stream($builder, $domains);
    }

    private function recordSync(Servidor $servidor, string $ip, int $count): void
    {
        $servidor->forceFill(['last_synced_at' => now()])->saveQuietly();
        ServerSyncLog::create([
            'servidor_id' => $servidor->id,
            'ip_address' => $ip,
            'dominios_count' => $count,
            'created_at' => now(),
        ]);
    }

    private function stream(RpzZoneBuilder $builder, Builder $domains): StreamedResponse
    {
        return response()->stream(function () use ($builder, $domains): void {
            foreach ($builder->hostsLines($domains) as $line) {
                echo $line."\n";
            }
        }, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
