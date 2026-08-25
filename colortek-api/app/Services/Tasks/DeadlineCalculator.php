<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Models\Project;
use App\Models\Setting;
use App\Models\WorkflowTaskDefinition;
use App\Services\Time\WorkingCalendar;
use Carbon\CarbonImmutable;

final class DeadlineCalculator
{
    public function __construct(private WorkingCalendar $calendar) {}

    public function for(
        WorkflowTaskDefinition $definition,
        ?Project $project,
        CarbonImmutable $from,
    ): ?CarbonImmutable {
        $minutes = null;

        if ($project !== null && is_array($project->sla_profile)) {
            $override = $project->sla_profile[$definition->code] ?? null;
            if ($override !== null) {
                $minutes = (int) $override;
            }
        }

        $minutes ??= $definition->sla_minutes;

        if ($minutes === null) {
            return null;
        }

        return $this->calendar->addWorkingMinutes($from, $minutes);
    }

    public function companyBlocksAllWhenSiteNotReady(): bool
    {
        return (bool) Setting::get('block_all_when_site_not_ready', false);
    }
}
