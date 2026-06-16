<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('loan-workflow:send-reminders')
    ->dailyAt('09:00')
    ->withoutOverlapping();

Schedule::command('loan-workflow:cleanup-temp-files')
    ->hourly()
    ->withoutOverlapping();
