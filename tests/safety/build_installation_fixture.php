<?php
// Synthetic data only. Never part of the normal installer.
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (getenv('RPZ_INSTALLATION_SMOKE') !== '1' || parse_url(config('app.url'), PHP_URL_HOST) !== 'panel.install.test') {
    fwrite(STDERR, "Fixture permitida apenas no laboratório panel.install.test.\n");
    exit(1);
}
foreach (['lab-install' => ['127.0.0.1/32', '172.16.0.0/12'], 'negado-install' => ['203.0.113.23/32']] as $slug => $ips) {
    $company = App\Models\Empresa::firstOrCreate(['rpz_slug' => $slug], ['nome' => 'Laboratório '.$slug, 'status' => 'active']);
    App\Models\Licenca::firstOrCreate(['empresa_id' => $company->id], ['starts_at' => today(), 'max_servidores' => 3, 'status' => 'active']);
    $list = App\Models\Lista::firstOrCreate(['nome' => 'Teste '.$slug], ['empresa_id' => $company->id, 'origem' => 'manual', 'status' => 'active']);
    $server = App\Models\Servidor::firstOrCreate(['empresa_id' => $company->id, 'nome' => 'DNS de teste'], ['status' => 'active', 'tipo_dns' => 'unbound', 'bloqueio_modo' => 'nxdomain', 'ip_restriction_enabled' => true]);
    $server->listas()->syncWithoutDetaching([$list->id]);
    foreach ($ips as $ip) $server->allowedIps()->firstOrCreate(['ip_cidr' => $ip], ['status' => 'active']);
    $list->dominios()->firstOrCreate(['dominio' => 'blocked.install.test'], ['ativo' => true]);
}
echo "Fixture sintética criada no laboratório.\n";
