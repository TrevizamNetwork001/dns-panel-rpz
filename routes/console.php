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
