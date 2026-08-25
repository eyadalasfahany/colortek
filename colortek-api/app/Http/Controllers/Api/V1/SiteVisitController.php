<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SiteVisitDraftRequest;
use App\Http\Requests\SiteVisitMeasurementsRequest;
use App\Http\Requests\SiteVisitSubmitRequest;
use App\Http\Resources\SiteVisitResource;
use App\Http\Resources\TaskResource;
use App\Models\Project;
use App\Models\SiteVisit;
use App\Services\Site\SiteMeasurementService;
use App\Services\Site\SiteVisitPdfGenerator;
use App\Services\Site\SiteVisitService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SiteVisitController extends Controller
{
    public function __construct(
        private SiteVisitService $siteVisitService,
        private SiteMeasurementService $measurementService,
        private SiteVisitPdfGenerator $pdfGenerator,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SiteVisit::class);

        $paginator = $this->siteVisitService->paginate((int) $request->integer('per_page', 15));

        return SiteVisitResource::collection($paginator)->response();
    }

    public function show(int $id): JsonResponse
    {
        $visit = $this->siteVisitService->findOrFail($id, [
            'project', 'engineer', 'answers.checklistItem', 'measurements.deductions', 'attachments',
        ]);
        $this->authorize('view', $visit);

        return response()->json(['data' => SiteVisitResource::make($visit)]);
    }

    public function forProject(int $projectId): JsonResponse
    {
        $this->authorize('viewAny', SiteVisit::class);

        $visits = $this->siteVisitService->forProject($projectId);

        return response()->json(['data' => SiteVisitResource::collection($visits)]);
    }

    public function store(Request $request, int $projectId): JsonResponse
    {
        $project = Project::query()->find($projectId);
        if ($project === null) {
            throw new ModelNotFoundException(__('Project not found'));
        }

        $this->authorize('create', SiteVisit::class);

        $result = $this->siteVisitService->createForProject($project, $request->user());

        return response()->json([
            'data' => SiteVisitResource::make($result['visit']),
            'meta' => ['task' => TaskResource::make($result['task'])],
        ], 201);
    }

    public function update(SiteVisitDraftRequest $request, int $id): JsonResponse
    {
        $visit = $this->siteVisitService->findOrFail($id);
        $this->authorize('update', $visit);

        $updated = $this->siteVisitService->updateDraft(
            $visit,
            $request->validated(),
            $request->user(),
            $request->user()->can('site.measurements_edit'),
        );

        return response()->json(['data' => SiteVisitResource::make($updated)]);
    }

    public function measurements(SiteVisitMeasurementsRequest $request, int $id): JsonResponse
    {
        $visit = $this->siteVisitService->findOrFail($id);
        $this->authorize('update', $visit);

        $result = $this->measurementService->bulkUpsert(
            $visit,
            $request->validated('rows'),
            $request->header('Idempotency-Key'),
        );

        $visit = $visit->fresh(['measurements.deductions', 'engineer', 'project', 'answers.checklistItem']);

        return response()->json([
            'data' => SiteVisitResource::make($visit),
            'meta' => ['idempotent' => $result['idempotent']],
        ]);
    }

    public function submit(SiteVisitSubmitRequest $request, int $id): JsonResponse
    {
        $visit = $this->siteVisitService->findOrFail($id);
        $this->authorize('submit', $visit);

        $signedId = $request->validated('signed_attachment_id');
        $result = $this->siteVisitService->submit(
            $visit,
            $request->validated('answers'),
            $request->user(),
            is_numeric($signedId) ? (int) $signedId : null,
        );

        $meta = [];
        if ($result['humidity_warning'] === true) {
            $meta['humidity_warning'] = true;
        }

        return response()->json([
            'data' => SiteVisitResource::make($result['visit']),
            'meta' => $meta,
        ]);
    }

    public function pdf(int $id): Response
    {
        $visit = $this->siteVisitService->findOrFail($id, ['measurements.deductions', 'answers.checklistItem']);
        $this->authorize('view', $visit);

        return $this->pdfGenerator->generate($visit);
    }
}
