<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Task;
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
    }
}
