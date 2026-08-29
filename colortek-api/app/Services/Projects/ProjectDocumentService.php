<?php

declare(strict_types=1);

namespace App\Services\Projects;

use App\Models\Attachment;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Sample;
use App\Models\SiteVisit;
use App\Models\Task;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Every document that belongs to a project, wherever it was uploaded.
 *
 * Attachments are polymorphic and hang off the thing they document — a payment
 * proof off the payment, a signed approval off the sample, site photos off the
 * visit — so there is no single foreign key to follow. This gathers the ids of
 * everything the project owns and returns the attachments in one query.
 */
final class ProjectDocumentService
{
    /** @param array<string, mixed> $filters */
    public function paginateForProject(
        Project $project,
        int $perPage = 25,
        array $filters = [],
    ): LengthAwarePaginator {
        $taskIds = Task::query()->where('project_id', $project->id)->pluck('id');
        $sampleIds = Sample::query()->where('project_id', $project->id)->pluck('id');
        $paymentIds = Payment::query()->where('project_id', $project->id)->pluck('id');
        $visitIds = SiteVisit::query()->where('project_id', $project->id)->pluck('id');

        return Attachment::query()
            ->with('uploadedBy')
            ->where(function ($query) use ($project, $taskIds, $sampleIds, $paymentIds, $visitIds): void {
                $query
                    ->where(fn ($q) => $q
                        ->where('attachable_type', $project->getMorphClass())
                        ->where('attachable_id', $project->id))
                    ->orWhere(fn ($q) => $q
                        ->where('attachable_type', (new Task)->getMorphClass())
                        ->whereIn('attachable_id', $taskIds))
                    ->orWhere(fn ($q) => $q
                        ->where('attachable_type', (new Sample)->getMorphClass())
                        ->whereIn('attachable_id', $sampleIds))
                    ->orWhere(fn ($q) => $q
                        ->where('attachable_type', (new Payment)->getMorphClass())
                        ->whereIn('attachable_id', $paymentIds))
                    ->orWhere(fn ($q) => $q
                        ->where('attachable_type', (new SiteVisit)->getMorphClass())
                        ->whereIn('attachable_id', $visitIds));
            })
            ->when(
                ! empty($filters['type']),
                fn ($q) => $q->where('type', $filters['type']),
            )
            ->latest('id')
            ->paginate($perPage);
    }
}
