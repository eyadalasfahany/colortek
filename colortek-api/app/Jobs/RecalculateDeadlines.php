<?php
declare(strict_types=1);
namespace App\Jobs;
use App\Services\Admin\CalendarImpactService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
final class RecalculateDeadlines implements ShouldQueue {
    use Queueable;
    public function handle(CalendarImpactService $s): void { $s->recalculateAllOpenTasks(); }
}
