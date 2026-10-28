<?php
// Synthetic fixtures only: the connection is forcibly replaced with :memory:.
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
    'database.connections.sqlite.url' => null, 'queue.default' => 'null', 'logging.default' => 'null']);
Illuminate\Support\Facades\DB::purge('sqlite');
Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
$db = Illuminate\Support\Facades\DB::class;
$company = $db::table('empresas')->insertGetId(['nome' => 'Synthetic RPZ', 'status' => 'active']);
$list = $db::table('listas')->insertGetId(['nome' => 'Synthetic source', 'origem' => 'manual', 'status' => 'active']);
$server = $db::table('servidores')->insertGetId(['nome' => 'Synthetic DNS', 'empresa_id' => $company, 'status' => 'active', 'token' => 'synthetic-test-token']);
$db::table('lista_servidor')->insert(['lista_id' => $list, 'servidor_id' => $server]);
$db::table('dominios')->insert(['lista_id' => $list, 'dominio' => 'blocked.example', 'ativo' => true]);
$db::table('empresa_domain_exceptions')->insert(['empresa_id' => $company, 'dominio' => 'allowed.blocked.example']);
$builder = app(App\Services\RpzZoneBuilder::class);
foreach (['nxdomain' => '.', 'redirect' => 'sink.example.'] as $mode => $target) {
    $canonical = $builder->buildCanonical($builder->serverQuery($server), 'policy.test', $target);
    $file = fopen('/review/'.$mode.'.zone', 'wb');
    foreach ($builder->renderZone($canonical, 'policy.test', '7', $target) as $chunk) {
        App\Services\RpzStream::writeAll($file, $chunk);
    }
    fclose($file);
    fclose($canonical['stream']);
}
