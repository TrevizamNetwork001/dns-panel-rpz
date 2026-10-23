<?php
// No Laravel bootstrap, config cache, fallbacks, database writes or secret output.
require '/var/www/html/vendor/autoload.php';
use Illuminate\Encryption\Encrypter;

$key = (string) getenv('APP_KEY');
$raw = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;
if (! is_string($raw) || strlen($raw) !== 32) {
    fwrite(STDERR, "APP_KEY incompativel com AES-256-CBC\n");
    exit(1);
}
$pdo = new PDO('sqlite:file:/data/database.sqlite?mode=ro');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$encrypter = new Encrypter($raw, 'AES-256-CBC');
$invalidSecrets = 0;
foreach ($pdo->query("SELECT value FROM settings WHERE key IN ('telegram_bot_token', 'r2_access_key_id', 'r2_secret_access_key')") as $row) {
    $value = $row['value'];
    $payload = is_string($value) ? json_decode(base64_decode($value, true) ?: '', true) : null;
    if (is_array($payload) && isset($payload['iv'], $payload['value'], $payload['mac'])) {
        try { $encrypter->decryptString($value); } catch (Throwable) { $invalidSecrets++; }
    }
}
$missing = 0;
$historicalMissing = 0;
$invalidPaths = 0;
$hashMismatch = 0;
$checkFile = static function ($relative, $prefix, $expectedHash = null) use (&$missing, &$invalidPaths, &$hashMismatch): void {
    if (! is_string($relative) || $relative === '' || str_starts_with($relative, '/') || in_array('..', explode('/', $relative), true)) {
        $invalidPaths++;
        return;
    }
    $path = '/var/www/html/storage/app/private/'.$prefix.$relative;
    if (! is_file($path) || ! is_readable($path)) { $missing++; return; }
    if ($expectedHash && ! hash_equals($expectedHash, hash_file('sha256', $path))) { $hashMismatch++; }
};
foreach ($pdo->query('SELECT storage_path, status FROM anatel_imports') as $row) {
    $before = $missing;
    $checkFile($row['storage_path'], 'anatel/');
    // Terminal imports already keep their results in SQLite. Their source
    // documents are historical evidence, not an input to serving RPZ zones.
    // Pending/processing imports still require the original document.
    if ($missing > $before && in_array($row['status'], ['completed', 'rejected', 'blocked', 'failed'], true)) {
        $missing--;
        $historicalMissing++;
    }
}
if ($historicalMissing) {
    fwrite(STDERR, "Aviso: documentos ANATEL historicos ausentes={$historicalMissing}; registros e dominios preservados no banco. Reprocessamento exige novo envio do documento.\n");
}
foreach ($pdo->query('SELECT v.file_path, v.file_hash FROM rpz_zones z JOIN rpz_zone_versions v ON v.id=z.current_version_id') as $row) {
    $checkFile($row['file_path'], '', $row['file_hash']);
}
if ($invalidSecrets || $missing || $invalidPaths || $hashMismatch) {
    fwrite(STDERR, "Preflight bloqueado: credenciais incompativeis={$invalidSecrets}; arquivos ausentes={$missing}; caminhos invalidos={$invalidPaths}; hashes divergentes={$hashMismatch}. Nenhum dado alterado.\n");
    exit(1);
}
echo "APP_KEY compativel com valores criptografados presentes; referencias privadas verificadas.\n";
