<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Time\TimerService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class CloseStaleTimers implements ShouldQueue
{
    use Queueable;

    public function handle(TimerService $timers): void
    {
        $timers->closeStaleEntries(CarbonImmutable::now('Africa/Cairo'));
    }
}
