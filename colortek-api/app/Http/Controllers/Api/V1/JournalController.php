<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\JournalResource;
use App\Models\Journal;
use App\Services\Payments\JournalQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class JournalController extends Controller
{
    public function __construct(private JournalQueryService $journalQueryService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Journal::class);

        $paginator = $this->journalQueryService->paginate((int) $request->integer('per_page', 15));

        return JournalResource::collection($paginator)->response();
    }

    public function show(Request $request, string $date): JsonResponse
    {
        $this->authorize('viewAny', Journal::class);

        $journal = $this->journalQueryService->findByDate($date);

        return response()->json([
            'data' => JournalResource::make($journal),
        ]);
    }
}
