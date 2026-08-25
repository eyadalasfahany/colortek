<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClientDecisionRequest;
use App\Http\Requests\ModificationRequest;
use App\Http\Requests\StartSampleRequest;
use App\Http\Resources\SampleChainResource;
use App\Http\Resources\SampleListResource;
use App\Http\Resources\SampleResource;
use App\Http\Resources\TaskResource;
use App\Models\Project;
use App\Models\Sample;
use App\Services\Samples\ApprovalFormGenerator;
use App\Services\Samples\SampleService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class SampleController extends Controller
{
    public function __construct(
        private SampleService $sampleService,
        private ApprovalFormGenerator $approvalFormGenerator,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Sample::class);

        $paginator = $this->sampleService->paginate((int) $request->integer('per_page', 15));

        return SampleListResource::collection($paginator)->response();
    }

    public function store(StartSampleRequest $request): JsonResponse
    {
        $this->authorize('create', Sample::class);

        if (! $request->filled('project_id') && ! $request->user()->can('sample.create_presale')) {
            abort(403, __('You cannot create a pre-sale sample without permission.'));
        }

        $result = $this->sampleService->start($request->validated(), $request->user());

        return response()->json([
            'data' => SampleResource::make($result['sample']),
            'meta' => [
                'task' => TaskResource::make($result['task']),
            ],
        ], 201);
    }

    public function show(string $identifier): JsonResponse
    {
        $sample = $this->sampleService->findOrFail($identifier, $this->sampleService->detailRelations());
        $this->authorize('view', $sample);

        return response()->json([
            'data' => SampleResource::make($sample),
        ]);
    }

    public function chain(string $identifier): JsonResponse
    {
        $sample = $this->sampleService->findOrFail($identifier);
        $this->authorize('view', $sample);

        return response()->json([
            'data' => SampleChainResource::make($this->sampleService->chainPayload($sample)),
        ]);
    }

    public function forProject(int $projectId): JsonResponse
    {
        $project = Project::query()->find($projectId);
        if ($project === null) {
            throw new ModelNotFoundException(__('Project not found'));
        }

        $this->authorize('viewAny', Sample::class);

        return response()->json([
            'data' => SampleListResource::collection($this->sampleService->forProject($projectId)),
        ]);
    }

    public function modification(ModificationRequest $request, string $identifier): JsonResponse
    {
        $parent = $this->sampleService->findOrFail($identifier);
        $this->authorize('requestModification', $parent);

        $child = $this->sampleService->requestModification($parent, $request->validated(), $request->user());

        return response()->json([
            'data' => SampleResource::make($child->load($this->sampleService->detailRelations())),
        ], 201);
    }

    public function approvalForm(Request $request, string $identifier): Response
    {
        $sample = $this->sampleService->findOrFail($identifier, ['client', 'project.quotation']);
        $this->authorize('recordClientDecision', $sample);

        $pdf = $this->approvalFormGenerator->generate($sample);
        $this->sampleService->markApprovalFormGenerated($sample, $request->user());

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$sample->reference.'-approval-form.pdf"',
        ]);
    }

    public function clientDecision(ClientDecisionRequest $request, string $identifier): JsonResponse
    {
        $sample = $this->sampleService->findOrFail($identifier);
        $this->authorize('recordClientDecision', $sample);

        $attachmentIds = $request->input('attachment_ids', []);
        $updated = $this->sampleService->recordClientDecision(
            $sample,
            $request->validated(),
            $request->user(),
            is_array($attachmentIds) ? $attachmentIds : [],
        );

        return response()->json([
            'data' => SampleResource::make($updated),
        ]);
    }
}
