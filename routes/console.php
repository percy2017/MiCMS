<?php

use App\Services\ScheduleLoaderService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('schedule:run', function () {
    $this->comment('Scheduled tasks loaded from database.');
})->purpose('Run scheduled tasks from database');

if (app()->runningInConsole() && ! app()->runningUnitTests()) {
    try {
        app(ScheduleLoaderService::class)->load(app(Schedule::class));
    } catch (Throwable) {
        //
    }
}
