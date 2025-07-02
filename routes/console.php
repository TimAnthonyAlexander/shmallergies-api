<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule German product scraping
Schedule::command('scrape:german-scheduled')
    ->daily()
    ->at('02:00')  // Run at 2 AM to avoid peak hours
    ->withoutOverlapping()
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/german-scraping.log'));
