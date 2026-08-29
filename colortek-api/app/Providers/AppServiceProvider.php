<?php

declare(strict_types=1);

namespace App\Providers;

use App\Gateways\Odoo\FakeOdooGateway;
use App\Gateways\Odoo\HttpOdooGateway;
use App\Gateways\Odoo\OdooGateway;
use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\CorrectiveAction;
use App\Models\CrewLog;
use App\Models\Employee;
use App\Models\Journal;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\SiteVisit;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Policies\AttachmentPolicy;
use App\Policies\AuditLogPolicy;
use App\Policies\ClientPolicy;
use App\Policies\CorrectiveActionPolicy;
use App\Policies\CrewLogPolicy;
use App\Policies\EmployeePolicy;
use App\Policies\JournalPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\QuotationPolicy;
use App\Policies\RolePolicy;
use App\Policies\SiteVisitPolicy;
use App\Policies\TaskPolicy;
use App\Policies\TimeEntryPolicy;
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

        // specs/13: one interface, driver chosen by config so Phase 2 swaps
        // the implementation without touching business logic.
        $this->app->singleton(OdooGateway::class, fn (): OdooGateway => match (config('services.odoo.driver')) {
            'http' => new HttpOdooGateway,
            default => new FakeOdooGateway,
        });
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
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(CrewLog::class, CrewLogPolicy::class);
        Gate::policy(TimeEntry::class, TimeEntryPolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
        Gate::policy(Employee::class, EmployeePolicy::class);
        Gate::policy(Client::class, ClientPolicy::class);
        Gate::policy(Quotation::class, QuotationPolicy::class);
        // Spatie's Role lives outside App\Models, so Laravel's policy
        // auto-discovery never finds RolePolicy — it must be bound by hand.
        Gate::policy(Role::class, RolePolicy::class);
    }
}
