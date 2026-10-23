<?php
// No migrations, writes, settings fallback, URLs, jobs or publication.
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.connections.sqlite.database' => 'file:/recovered/database.sqlite?mode=ro&immutable=1',
    'database.connections.sqlite.journal_mode' => null, 'database.connections.sqlite.synchronous' => null,
    'logging.default' => 'null', 'queue.default' => 'null']);
Illuminate\Support\Facades\DB::purge('sqlite');
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
$result = ['integrity' => DB::select('PRAGMA integrity_check'),
    'foreign_key_violations' => count(DB::select('PRAGMA foreign_key_check')),
    'domains' => DB::table('dominios')->count(),
    'database_sha256' => hash_file('sha256', '/recovered/database.sqlite')];
$key = (string) config('app.key');
$decoded = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;
$result['key_present_and_valid_length'] = is_string($decoded) && strlen($decoded) === 32;
$result['protected_settings'] = [];
foreach (['telegram_bot_token', 'r2_access_key_id', 'r2_secret_access_key'] as $name) {
    $value = DB::table('settings')->where('key', $name)->value('value');
    $payload = is_string($value) ? json_decode(base64_decode($value, true) ?: '', true) : null;
    if (! is_array($payload) || ! isset($payload['iv'], $payload['value'], $payload['mac'])) {
        $state = $value === null || $value === '' ? 'absent' : 'legacy_plaintext';
    } else {
        try {
            Crypt::decryptString($value);
            $state = 'decrypts_with_configured_key';
        } catch (Throwable) {
            $state = 'cannot_decrypt_with_configured_key';
        }
    }
    $result['protected_settings'][$name] = $state;
}
$private = '/recovered-storage/app/private/';
$validPath = static fn ($path) => is_string($path) && $path !== '' && ! str_starts_with($path, '/')
    && ! in_array('..', explode('/', $path), true);
$pdfs = ['references' => 0, 'missing' => 0, 'invalid_paths' => 0];
foreach (DB::table('anatel_imports')->select('storage_path')->cursor() as $row) {
    $pdfs['references']++;
    if (! $validPath($row->storage_path)) { $pdfs['invalid_paths']++; continue; }
    if (! is_file($private.'anatel/'.$row->storage_path)) { $pdfs['missing']++; }
}
$result['anatel_private_files'] = $pdfs;
$versions = ['references' => 0, 'missing' => 0, 'invalid_paths' => 0, 'hash_mismatch' => 0];
foreach (DB::table('rpz_zones')->join('rpz_zone_versions', 'rpz_zone_versions.id', '=', 'rpz_zones.current_version_id')
    ->select('rpz_zone_versions.file_path', 'rpz_zone_versions.file_hash')->cursor() as $row) {
    $versions['references']++;
    if (! $validPath($row->file_path)) { $versions['invalid_paths']++; continue; }
    $path = $private.$row->file_path;
    if (! is_file($path)) { $versions['missing']++; continue; }
    if ($row->file_hash && ! hash_equals($row->file_hash, hash_file('sha256', $path))) { $versions['hash_mismatch']++; }
}
$result['current_rpz_artifacts'] = $versions;
foreach (['/recovered-storage/app/private', '/recovered-storage/app/public', '/recovered-backups'] as $path) {
    $files = 0;
    $bytes = 0;
    if (is_dir($path)) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile() && ! $file->isLink()) { $files++; $bytes += $file->getSize(); }
        }
    }
    $result['storage_inventory'][basename($path)] = ['files' => $files, 'bytes' => $bytes];
}
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
