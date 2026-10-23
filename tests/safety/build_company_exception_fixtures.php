<?php
// Run only against an explicitly supplied disposable SQLite file. No migrations,
// HTTP endpoints, version resolution, jobs, artifact publication or NOTIFY.
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\RpzZoneBuilder;
use Illuminate\Support\Facades\DB;

$database = getenv('RPZ_DISPOSABLE_DB');
$output = getenv('RPZ_SAFETY_OUTPUT');
if (! $database || ! $output || ! is_file($database) || realpath($database) !== '/review/disposable.sqlite') {
    throw new RuntimeException('Requires /review/disposable.sqlite, copied beforehand; never use the recovered database.');
}
config(['database.connections.sqlite.database' => $database,
    'database.connections.sqlite.journal_mode' => null,
    'database.connections.sqlite.synchronous' => null,
    'queue.default' => 'null', 'logging.default' => 'null']);
DB::purge('sqlite');
$builder = app(RpzZoneBuilder::class);
$exceptions = ['exemplo.com.br', 'allow.deep.example.com'];
$domains = ['com.br', 'exemplo.com.br', 'ads.exemplo.com.br', 'example.com'];
$manifest = [];
DB::beginTransaction();
try {
    $companies = [];
    foreach (['A', 'B'] as $company) {
        $companyId = DB::table('empresas')->insertGetId(['nome' => 'RPZ safety '.$company, 'status' => 'active', 'rpz_slug' => 'safety-'.strtolower($company)]);
        $companies[$company] = $companyId;
        if ($company === 'A') {
            foreach ($exceptions as $exception) {
                DB::table('empresa_domain_exceptions')->insert(['empresa_id' => $companyId, 'dominio' => $exception, 'motivo' => 'Synthetic safety fixture']);
            }
        }
        foreach (['manual', 'anatel', 'externa'] as $source) {
            $listId = DB::table('listas')->insertGetId(['empresa_id' => null, 'nome' => 'Safety '.$company.' '.$source, 'origem' => $source, 'status' => 'active']);
            foreach ($domains as $domain) {
                DB::table('dominios')->insert(['lista_id' => $listId, 'dominio' => $domain, 'ativo' => true]);
            }
            foreach (['A', 'B'] as $linkedCompany) {
                // Link the very same list to both companies: exceptions must not
                // mutate global/shared source contents.
                if (! isset($companies[$linkedCompany])) { continue; }
                $serverId = DB::table('servidores')->insertGetId(['empresa_id' => $companies[$linkedCompany], 'nome' => 'Safety '.$source,
                    'token' => bin2hex(random_bytes(24)), 'status' => 'active']);
                DB::table('lista_servidor')->insert(['lista_id' => $listId, 'servidor_id' => $serverId]);
                $key = 'server-'.$company.'-'.$linkedCompany.'-'.$source;
                $manifest[$key] = ['company' => $linkedCompany, 'source' => $source];
                $queries[$key] = $builder->serverQuery($serverId);
            }
        }
    }
    foreach ($companies as $company => $id) {
        $key = 'company-'.$company;
        $manifest[$key] = ['company' => $company, 'source' => 'aggregate'];
        $queries[$key] = $builder->companyQuery($id);
    }
    $edgeCases = [
        'www' => [['example.com', 'ads.example.com'], ['www.example.com'], ['www.example.com', 'a.www.example.com'], ['example.com', 'ads.example.com', 'other.example.com', 'otherwww.example.com']],
        'multiple-depth' => [['example.com'], ['allow.a.b.example.com', 'allow.other.example.com'], ['allow.a.b.example.com', 'x.y.allow.a.b.example.com', 'allow.other.example.com'], ['example.com', 'b.example.com', 'a.b.example.com', 'sibling.a.b.example.com', 'other.example.com', 'sibling.other.example.com']],
        'existing-empty-node' => [['example.com', 'blocked.deep.example.com'], ['allow.deep.example.com'], ['allow.deep.example.com', 'x.allow.deep.example.com'], ['example.com', 'deep.example.com', 'other.deep.example.com', 'x.other.deep.example.com', 'blocked.deep.example.com']],
        'wpad' => [['wpad.domain.name'], ['allow.deep.wpad.domain.name'], ['allow.deep.wpad.domain.name'], ['wpad.domain.name', 'deep.wpad.domain.name', 'other.wpad.domain.name', 'sibling.deep.wpad.domain.name']],
        'overlap' => [['example.com', 'blocked.allow.example.com'], ['allow.example.com', 'nested.allow.example.com'], ['allow.example.com', 'nested.allow.example.com', 'blocked.allow.example.com', 'x.blocked.allow.example.com'], ['example.com', 'other.example.com', 'notallow.example.com']],
        'redirect' => [['example.com'], ['allow.deep.example.com'], ['allow.deep.example.com', 'x.allow.deep.example.com'], ['example.com', 'deep.example.com', 'other.deep.example.com']],
    ];
    foreach ($edgeCases as $name => [$blocks, $allows, $allowedQueries, $probes]) {
        $companyId = DB::table('empresas')->insertGetId(['nome' => 'Edge '.$name, 'status' => 'active', 'rpz_slug' => 'edge-'.$name]);
        foreach ($allows as $allow) {
            DB::table('empresa_domain_exceptions')->insert(['empresa_id' => $companyId, 'dominio' => $allow]);
        }
        $listId = DB::table('listas')->insertGetId(['empresa_id' => null, 'nome' => 'Edge '.$name, 'origem' => 'manual', 'status' => 'active']);
        foreach ($blocks as $domain) { DB::table('dominios')->insert(['lista_id' => $listId, 'dominio' => $domain, 'ativo' => true]); }
        $serverId = DB::table('servidores')->insertGetId(['empresa_id' => $companyId, 'nome' => 'Edge '.$name, 'token' => bin2hex(random_bytes(24)), 'status' => 'active']);
        DB::table('lista_servidor')->insert(['lista_id' => $listId, 'servidor_id' => $serverId]);
        $key = 'edge-'.$name;
        $manifest[$key] = ['company' => 'edge', 'source' => 'manual', 'allowed' => $allowedQueries, 'probes' => $probes, 'baseline' => true, 'target' => $name === 'redirect' ? 'redirect.test.' : '.'];
        $queries[$key] = $builder->serverQuery($serverId);
    }
    foreach ($queries as $key => $query) {
        $target = $manifest[$key]['target'] ?? '.';
        $canonical = $builder->buildCanonical($query, 'policy.test', $target);
        try {
            $zone = implode('', iterator_to_array($builder->renderZone($canonical, 'policy.test', '1', '.')));
        } finally {
            fclose($canonical['stream']);
        }
        file_put_contents($output.'/'.$key.'.zone', $zone);
        $unscoped = DB::query()->fromSub($query, 'raw_domains')->select('dominio')->orderBy('dominio');
        $baseline = $builder->buildCanonical($unscoped, 'policy.test', $target);
        try {
            $baselineZone = implode('', iterator_to_array($builder->renderZone($baseline, 'policy.test', '1', $target)));
        } finally {
            fclose($baseline['stream']);
        }
        file_put_contents($output.'/'.$key.'-baseline.zone', $baselineZone);
        // Deliberately unsafe candidate, confined to fixture files: remove ALL
        // descendant blocks, then add exact + wildcard PASSTHRU. This proves
        // that even more than removing the exact block is insufficient.
        if ($manifest[$key]['company'] === 'A') {
            $lines = explode("\n", $baselineZone);
            $lines = array_filter($lines, function ($line) use ($exceptions) {
                $owner = preg_replace('/^\*\./', '', explode(' ', $line)[0]);
                foreach ($exceptions as $exception) {
                    if ($owner === $exception || str_ends_with($owner, '.'.$exception)) { return false; }
                }
                return true;
            });
            foreach ($exceptions as $exception) {
                $lines[] = $exception.' CNAME rpz-passthru.';
                $lines[] = '*.'.$exception.' CNAME rpz-passthru.';
            }
            file_put_contents($output.'/'.$key.'-candidate.zone', implode("\n", $lines)."\n");
        }
    }
    file_put_contents($output.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));
    echo count($manifest)." synthetic zones rendered in disposable storage; no versions or publication.\n";
} finally {
    DB::rollBack();
    DB::disconnect('sqlite');
}
