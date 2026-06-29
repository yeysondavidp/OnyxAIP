<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Daily backup: DB dump + storage snapshot at 02:00 (US-00.7) ─────────
Schedule::command('backup:application')
    ->dailyAt('02:00')
    ->withoutOverlapping();

// ── Daily threshold check at 08:00 (business hours) (US-00.7) ───────────
Schedule::command('storage:check-thresholds')
    ->dailyAt('08:00')
    ->withoutOverlapping();
