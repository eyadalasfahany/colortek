<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CorrectTimeEntryRequest;
use App\Http\Resources\TimeEntryResource;
use App\Models\TimeEntry;
use App\Services\Time\TimeEntryService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;

final class TimeEntryController extends Controller
{
    public function __construct(private TimeEntryService $service) {}

    public function update(CorrectTimeEntryRequest $request, int $id): JsonResponse
    {
        $entry = TimeEntry::query()->find($id);
        if ($entry === null) {
            throw new ModelNotFoundException(__('Time entry not found'));
        }

        $this->authorize('correct', $entry);

        $updated = $this->service->correct($entry, $request->user(), $request->validated());

        return response()->json([
            'data' => TimeEntryResource::make($updated),
        ]);
    }
}
