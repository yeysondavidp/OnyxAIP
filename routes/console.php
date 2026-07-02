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

// ── Hourly SLA at-risk/breach recompute (US-12.3) ────────────────────────
Schedule::command('sla:recompute')
    ->hourly()
    ->withoutOverlapping();

// ── Daily warranty-expiry check at 08:00 (US-13.3) ───────────────────────
Schedule::command('warranty:check-expiry')
    ->dailyAt('08:00')
    ->withoutOverlapping();

// ── Technician reminders + link-expiry warnings (US-13.4) ────────────────
// Hourly — reminder/warning windows are hours-based, not daily.
Schedule::command('technicians:send-reminders')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('technicians:send-link-expiry-warnings')
    ->hourly()
    ->withoutOverlapping();

// ── Daily expired-report cleanup at 03:00 (EPIC-14) ──────────────────────
Schedule::command('reports:prune')
    ->dailyAt('03:00')
    ->withoutOverlapping();
