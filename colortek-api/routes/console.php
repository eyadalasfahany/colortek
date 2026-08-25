<?php

declare(strict_types=1);

use App\Jobs\OpenDailyJournal;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new OpenDailyJournal)->dailyAt('08:00')->timezone('Africa/Cairo');
