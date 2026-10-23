<?php

// Run only inside an isolated container with a disposable database under /tmp.
require getcwd().'/vendor/autoload.php';
foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => '/tmp/feed-contention.sqlite',
    'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'database', 'DB_TRANSACTION_MODE' => 'IMMEDIATE',
    'LOG_CHANNEL' => 'null'] as $key => $value) {
    putenv($key.'='.$value);
}
touch('/tmp/feed-contention.sqlite');
$app = require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Facades\Artisan::call('migrate:fresh', ['--force' => true]);
$list = App\Models\Lista::factory()->create();
$domains = [];
for ($i = 0; $i < 100000; $i++) {
    $domains[] = sprintf('domain%06d.example.net', $i);
}
$writer = app(App\Services\SqliteDomainFeedWriter::class);
$first = $writer->publish($list, $domains, true);
if ($first['adicionados'] !== 100000) {
    throw new RuntimeException('Initial feed was not complete');
}
$next = array_slice($domains, 1000);
for ($i = 100000; $i < 101000; $i++) {
    $next[] = sprintf('domain%06d.example.net', $i);
}
Illuminate\Support\Facades\DB::disconnect();
$pid = pcntl_fork();
if ($pid === -1) {
    throw new RuntimeException('Cannot fork contention probe');
}
if ($pid === 0) {
    try {
        for ($i = 0; $i < 100; $i++) {
            Illuminate\Support\Facades\DB::transaction(fn () => App\Models\AuditLog::create([
                'action' => 'test.concurrent_write', 'description' => 'Disposable contention probe', 'created_at' => now(),
            ]), 5);
            usleep(10000);
        }
        exit(0);
    } catch (Throwable $error) {
        fwrite(STDERR, get_class($error).": concurrent writer failed\n");
        exit(1);
    }
}
$started = microtime(true);
$result = $writer->publish($list, $next, true);
$seconds = microtime(true) - $started;
pcntl_waitpid($pid, $status);
$active = $list->dominios()->where('ativo', true)->count();
$events = App\Models\AuditLog::where('action', 'test.concurrent_write')->count();
if (pcntl_wexitstatus($status) !== 0 || $active !== 100000 || $events !== 100
    || $result['adicionados'] !== 1000 || $result['removidos'] !== 1000) {
    throw new RuntimeException('Contention test failed consistency checks');
}
echo json_encode(['active' => $active, 'parallel_writes' => $events, 'added' => $result['adicionados'],
    'removed' => $result['removidos'], 'seconds' => round($seconds, 3),
    'peak_mib' => round(memory_get_peak_usage(true) / 1048576, 1)], JSON_PRETTY_PRINT).PHP_EOL;
