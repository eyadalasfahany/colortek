<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Attachment;
use App\Models\Journal;
use App\Models\Payment;
use App\Models\CorrectiveAction;
use App\Models\SiteVisit;
use App\Models\Task;
use App\Policies\AttachmentPolicy;
use App\Policies\JournalPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\CorrectiveActionPolicy;
use App\Policies\SiteVisitPolicy;
use App\Policies\TaskPolicy;
use App\Services\Time\WorkingCalendar;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(WorkingCalendar::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Task::class, TaskPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(Journal::class, JournalPolicy::class);
        Gate::policy(Attachment::class, AttachmentPolicy::class);
        Gate::policy(SiteVisit::class, SiteVisitPolicy::class);
        Gate::policy(CorrectiveAction::class, CorrectiveActionPolicy::class);
    }
}
