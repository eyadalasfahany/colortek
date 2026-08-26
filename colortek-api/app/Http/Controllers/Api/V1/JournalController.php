<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\JournalReopenRequest;
use App\Http\Requests\JournalSubmitRequest;
use App\Http\Resources\JournalResource;
use App\Models\Journal;
use App\Services\Payments\JournalQueryService;
use App\Services\Payments\JournalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class JournalController extends Controller
{
    public function __construct(
        private JournalQueryService $journalQueryService,
        private JournalService $journalService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Journal::class);

        return JournalResource::collection(
            $this->journalQueryService->paginate((int) $request->integer('per_page', 15)),
        )->response();
    }

    public function show(Request $request, string $date): JsonResponse
    {
        $this->authorize('viewAny', Journal::class);

        return response()->json(['data' => JournalResource::make($this->journalQueryService->findByDate($date))]);
    }

    public function submit(JournalSubmitRequest $request, string $date): JsonResponse
    {
        $journal = $this->journalQueryService->findByDate($date);
        $this->authorize('submit', $journal);
        $this->journalService->submit($journal, $request->user(), $request->validated());

        return response()->json(['data' => JournalResource::make($journal->fresh())]);
    }

    public function reopen(JournalReopenRequest $request, string $date): JsonResponse
    {
        $journal = $this->journalQueryService->findByDate($date);
        $this->authorize('reopen', $journal);
        $this->journalService->reopen($journal, $request->user(), $request->validated('reason'));

        return response()->json(['data' => JournalResource::make($journal->fresh())]);
    }
}
