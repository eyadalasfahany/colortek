<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Attachment;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Journal;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\SiteChecklistItem;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkflowTemplate;
use App\Policies\AdminDiagnosticsPolicy;
use App\Policies\AttachmentPolicy;
use App\Policies\EmployeePolicy;
use App\Policies\HolidayPolicy;
use App\Policies\JournalPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\RolePolicy;
use App\Policies\SettingPolicy;
use App\Policies\SiteChecklistItemPolicy;
use App\Policies\TaskPolicy;
use App\Policies\UserPolicy;
use App\Policies\WorkflowTemplatePolicy;
use App\Services\Time\WorkingCalendar;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;

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
        Gate::policy(Setting::class, SettingPolicy::class);
        Gate::policy(Holiday::class, HolidayPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Employee::class, EmployeePolicy::class);
        Gate::policy(WorkflowTemplate::class, WorkflowTemplatePolicy::class);
        Gate::policy(SiteChecklistItem::class, SiteChecklistItemPolicy::class);

        Gate::define('viewAdminDiagnostics', [AdminDiagnosticsPolicy::class, 'viewAny']);
    }
}
