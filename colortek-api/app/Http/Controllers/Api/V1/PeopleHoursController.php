<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\PeopleHoursRequest;
use App\Services\Time\PeopleHoursService;
use Illuminate\Http\JsonResponse;

final class PeopleHoursController extends Controller
{
    public function __construct(private PeopleHoursService $service) {}

    public function __invoke(PeopleHoursRequest $request): JsonResponse
    {
        abort_unless($request->user()?->can('time.view_all'), 403);

        /** @var array{from: string, to: string, project_id?: int|null, department_id?: int|null, employee_id?: int|null} $filters */
        $filters = $request->validated();

        return response()->json([
            'data' => $this->service->report($request->user(), $filters),
        ]);
    }
}
