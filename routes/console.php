<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('servidores:limpar-inativos')
    ->dailyAt('03:30')->withoutOverlapping(60);

Schedule::command('unbound:check-release')
    ->weeklyOn(1, '08:00')->withoutOverlapping(60);

// Recover durable dirty markers after failed/lost jobs, including first publication.
Schedule::command('rpz:build --dirty')->everyFiveMinutes()->withoutOverlapping(10);

Schedule::command('anatel:consultar-caixa --isolated')
    ->dailyAt(config('anatel.imap_scan_time'))
    ->when(fn (): bool => config('anatel.imap_list_id') > 0)
    ->withoutOverlapping(10);

Schedule::command('anatel:avisar-publicacao --isolated')
    ->everyFiveMinutes()
    ->when(fn (): bool => config('anatel.publication_notices_enabled'))
    ->withoutOverlapping(10);
