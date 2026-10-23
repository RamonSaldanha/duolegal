<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Lembrete com presente para quem parou de jogar. Precisa do cron do
// `schedule:run` no servidor (ver README).
Schedule::command('emails:reengage')
    ->dailyAt('10:00')
    ->timezone('America/Sao_Paulo')
    ->withoutOverlapping();
