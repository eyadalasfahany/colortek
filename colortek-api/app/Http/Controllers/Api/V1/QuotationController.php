<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\QuotationRequest;
use App\Http\Resources\QuotationResource;
use App\Models\Quotation;
use App\Services\Quotations\QuotationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class QuotationController extends Controller
{
    public function __construct(private QuotationService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Quotation::class);

        return QuotationResource::collection($this->service->paginate(
            (int) $request->integer('per_page', 15),
            $request->only(['client_id', 'status', 'q']),
        ))->response();
    }

    public function store(QuotationRequest $request): JsonResponse
    {
        $this->authorize('create', Quotation::class);

        return response()->json([
            'data' => QuotationResource::make($this->service->store($request->validated(), $request->user())),
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $quotation = $this->service->findOrFail($id);
        $this->authorize('view', $quotation);

        return response()->json(['data' => QuotationResource::make($quotation)]);
    }

    public function update(QuotationRequest $request, int $id): JsonResponse
    {
        $quotation = $this->service->findOrFail($id);
        $this->authorize('update', $quotation);

        return response()->json([
            'data' => QuotationResource::make($this->service->update($quotation, $request->validated(), $request->user())),
        ]);
    }
}
