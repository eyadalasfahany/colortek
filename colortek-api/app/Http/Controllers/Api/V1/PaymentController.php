<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StartPaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\TaskResource;
use App\Models\Payment;
use App\Models\Project;
use App\Services\Payments\PaymentService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PaymentController extends Controller
{
    public function __construct(private PaymentService $paymentService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Payment::class);

        $paginator = $this->paymentService->paginate((int) $request->integer('per_page', 15));

        return PaymentResource::collection($paginator)->response();
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $payment = $this->paymentService->findOrFail($id, ['project', 'quotation', 'attachments']);
        $this->authorize('view', $payment);

        return response()->json([
            'data' => PaymentResource::make($payment),
        ]);
    }

    public function storeForProject(StartPaymentRequest $request, int $projectId): JsonResponse
    {
        $project = Project::query()->find($projectId);
        if ($project === null) {
            throw new ModelNotFoundException(__('Project not found'));
        }

        $this->authorize('start', [Payment::class, $project]);

        $result = $this->paymentService->startForProject(
            $project,
            $request->integer('installment_number'),
            $request->user(),
        );

        return response()->json([
            'data' => PaymentResource::make($result['payment']),
            'meta' => [
                'task' => TaskResource::make($result['task']),
            ],
        ], 201);
    }
}
