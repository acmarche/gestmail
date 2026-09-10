<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Tick par gestmail-scheduler.timer (etc/systemd/system/), chaque minute.
 *
 * L'heure est en UTC (config('app.timezone')), soit 03:00 ou 04:00 à Bruxelles
 * selon l'heure d'été. Volontaire : 02:00 locale est l'heure du changement
 * d'heure, où la tâche serait sautée en mars et exécutée deux fois en octobre.
 */
Schedule::command('citoyen:sync')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/citoyen-sync.log'));
