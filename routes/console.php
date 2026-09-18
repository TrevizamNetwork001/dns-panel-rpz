<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('rbl:check --only-enabled --limit=100')
    ->everySixHours()->withoutOverlapping(1440);

Schedule::command('servidores:limpar-inativos')
    ->dailyAt('03:30')->withoutOverlapping(60);
