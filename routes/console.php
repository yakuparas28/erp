<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('subscriptions:deactivate-expired')->dailyAt('00:10');

Schedule::command('fleet:send-critical-window-reminders')
    ->dailyAt('07:00')->withoutOverlapping()->onOneServer();

Schedule::command('fleet:check-mtv')
    ->dailyAt('07:05')->withoutOverlapping()->onOneServer();
