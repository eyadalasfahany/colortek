<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AuthorFormulaRequest;
use App\Http\Requests\PatchFormulaRequest;
use App\Http\Requests\RegisterFormulaRequest;
use App\Http\Resources\FormulaResource;
use App\Models\Formula;
use App\Services\Samples\FormulaService;
use App\Services\Samples\SampleService;
use Illuminate\Http\JsonResponse;

final class FormulaController extends Controller
{
    public function __construct(
        private FormulaService $formulaService,
        private SampleService $sampleService,
    ) {}

    public function index(string $sampleIdentifier): JsonResponse
    {
        $sample = $this->sampleService->findOrFail($sampleIdentifier);
        $this->authorize('viewAny', Formula::class);

        return response()->json([
            'data' => FormulaResource::collection($this->formulaService->forSample($sample)),
        ]);
    }

    public function store(AuthorFormulaRequest $request, string $sampleIdentifier): JsonResponse
    {
        $sample = $this->sampleService->findOrFail($sampleIdentifier);
        $this->authorize('author', Formula::class);

        $attachmentIds = $request->input('attachment_ids', []);

        $formula = $this->formulaService->author(
            $sample,
            $request->validated(),
            is_array($attachmentIds) ? $attachmentIds : [],
            $request->user(),
        );

        return response()->json([
            'data' => FormulaResource::make($formula),
        ], 201);
    }

    public function register(RegisterFormulaRequest $request, int $id): JsonResponse
    {
        $formula = $this->formulaService->findOrFail($id, ['sample', 'authorEmployee', 'attachments']);
        $this->authorize('register', $formula);

        $updated = $this->formulaService->register($formula, $request->validated(), $request->user());

        return response()->json([
            'data' => FormulaResource::make($updated),
        ]);
    }

    public function update(PatchFormulaRequest $request, int $id): JsonResponse
    {
        $formula = $this->formulaService->findOrFail($id, ['sample', 'authorEmployee', 'attachments']);
        $this->authorize('updateRegistered', $formula);

        $updated = $this->formulaService->updateRegistered($formula, $request->validated(), $request->user());

        return response()->json([
            'data' => FormulaResource::make($updated),
        ]);
    }
}
