<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\JournalReopened;
use App\Events\JournalSubmitted;
use App\Events\PaymentConfirmed;
use App\Events\PaymentQueried;
use App\Events\ProjectCompleted;
use App\Events\ProjectStageChanged;
use App\Events\TaskBlocked;
use App\Events\TaskClaimed;
use App\Events\TaskCompleted;
use App\Events\TaskCreated;
use App\Events\TaskOverdue;
use App\Events\TaskStarted;
use App\Events\TaskUnblocked;
use App\Listeners\DispatchNotifications;
use App\Listeners\RecordPaymentActivity;
use App\Listeners\RecordProjectActivity;
use App\Listeners\RecordTaskActivity;
use App\Models\Attachment;
use App\Models\Journal;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Task;
use App\Policies\AttachmentPolicy;
use App\Policies\JournalPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\TaskPolicy;
use App\Services\Time\WorkingCalendar;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(WorkingCalendar::class);
    }

    public function boot(): void
    {
        Gate::policy(Task::class, TaskPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(Journal::class, JournalPolicy::class);
        Gate::policy(Attachment::class, AttachmentPolicy::class);
        Gate::policy(Project::class, ProjectPolicy::class);

        $taskActivity = RecordTaskActivity::class;
        Event::listen(TaskCreated::class, [$taskActivity, 'handleTaskCreated']);
        Event::listen(TaskClaimed::class, [$taskActivity, 'handleTaskClaimed']);
        Event::listen(TaskCompleted::class, [$taskActivity, 'handleTaskCompleted']);
        Event::listen(TaskBlocked::class, [$taskActivity, 'handleTaskBlocked']);
        Event::listen(TaskStarted::class, [$taskActivity, 'handleTaskStarted']);
        Event::listen(TaskUnblocked::class, [$taskActivity, 'handleTaskUnblocked']);
        Event::listen(TaskOverdue::class, [$taskActivity, 'handleTaskOverdue']);

        $paymentActivity = RecordPaymentActivity::class;
        Event::listen(PaymentConfirmed::class, [$paymentActivity, 'handlePaymentConfirmed']);
        Event::listen(PaymentQueried::class, [$paymentActivity, 'handlePaymentQueried']);
        Event::listen(JournalSubmitted::class, [$paymentActivity, 'handleJournalSubmitted']);
        Event::listen(JournalReopened::class, [$paymentActivity, 'handleJournalReopened']);

        $projectActivity = RecordProjectActivity::class;
        Event::listen(ProjectStageChanged::class, [$projectActivity, 'handleProjectStageChanged']);
        Event::listen(ProjectCompleted::class, [$projectActivity, 'handleProjectCompleted']);

        $notifications = DispatchNotifications::class;
        Event::listen(TaskCreated::class, [$notifications, 'handleTaskCreated']);
        Event::listen(TaskClaimed::class, [$notifications, 'handleTaskClaimed']);
        Event::listen(TaskBlocked::class, [$notifications, 'handleTaskBlocked']);
        Event::listen(TaskOverdue::class, [$notifications, 'handleTaskOverdue']);
    }
}
