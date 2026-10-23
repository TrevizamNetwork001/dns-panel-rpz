<?php
// Run only in a disposable container with /data as tmpfs, never live volumes.
if (getenv('PREFLIGHT_DISPOSABLE_TEST') !== '1') {
    throw new RuntimeException('Disposable preflight test container required');
}
require '/var/www/html/vendor/autoload.php';
if (file_exists('/data/database.sqlite')) {
    throw new RuntimeException('Test refuses an existing database');
}
$db = new PDO('sqlite:/data/database.sqlite');
$db->exec('CREATE TABLE settings (key TEXT, value TEXT);
CREATE TABLE anatel_imports (storage_path TEXT, status TEXT);
CREATE TABLE rpz_zones (current_version_id INTEGER);
CREATE TABLE rpz_zone_versions (id INTEGER, file_path TEXT, file_hash TEXT)');
putenv('APP_KEY=base64:'.base64_encode(random_bytes(32)));
$cases = [
    ['completed', 'missing.pdf', 0, 'historicos ausentes=1'],
    ['rejected', 'missing.pdf', 0, 'historicos ausentes=1'],
    ['blocked', 'missing.pdf', 0, 'historicos ausentes=1'],
    ['failed', 'missing.pdf', 0, 'historicos ausentes=1'],
    ['pending', 'missing.pdf', 1, 'arquivos ausentes=1'],
    ['processing', 'missing.pdf', 1, 'arquivos ausentes=1'],
    ['awaiting_approval', 'missing.pdf', 1, 'arquivos ausentes=1'],
    ['completed', '../escape.pdf', 1, 'caminhos invalidos=1'],
];
foreach ($cases as [$status, $path, $expectedCode, $message]) {
    $db->exec('DELETE FROM anatel_imports');
    $db->prepare('INSERT INTO anatel_imports VALUES (?, ?)')->execute([$path, $status]);
    $lines = [];
    exec('php /review/docker/contabo-preflight.php 2>&1', $lines, $code);
    if ($code !== $expectedCode || ! str_contains(implode("\n", $lines), $message)) {
        throw new RuntimeException('Unexpected preflight result for '.$status.': '.implode("\n", $lines));
    }
}
$db->exec("DELETE FROM anatel_imports; INSERT INTO rpz_zones VALUES (1);
INSERT INTO rpz_zone_versions VALUES (1, 'rpz/1/versions/1.zone', 'hash')");
$lines = [];
exec('php /review/docker/contabo-preflight.php 2>&1', $lines, $code);
if ($code !== 1 || ! str_contains(implode("\n", $lines), 'arquivos ausentes=1')) {
    throw new RuntimeException('Missing current zone must still block startup');
}
echo "9 verificacoes passaram: historico opcional, importacoes abertas e zonas atuais obrigatorias.\n";
