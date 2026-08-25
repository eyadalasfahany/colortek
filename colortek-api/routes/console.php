<?php

declare(strict_types=1);

use App\Jobs\CloseStaleTimers;
use App\Jobs\EscalateOverdue;
use App\Jobs\OpenDailyJournal;
use App\Jobs\RecalculateOverdueTasks;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new OpenDailyJournal)->dailyAt('08:00')->timezone('Africa/Cairo');
Schedule::job(new RecalculateOverdueTasks)->everyTenMinutes();
Schedule::job(new EscalateOverdue)->everyThirtyMinutes();
Schedule::job(new CloseStaleTimers)->hourly();
