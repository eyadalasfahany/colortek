<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Activity\SseStream;
use Illuminate\Http\Request;

final class StreamController extends Controller
{
    public function __construct(private SseStream $sseStream) {}

    public function __invoke(Request $request)
    {
        $lastEventId = $request->header('Last-Event-ID');
        $cursor = is_numeric($lastEventId) ? (int) $lastEventId : null;

        return $this->sseStream->response($request->user(), $cursor);
    }
}
