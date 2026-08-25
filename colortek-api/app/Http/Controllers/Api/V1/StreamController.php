<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Activity\SseStream;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class StreamController extends Controller
{
    public function __construct(private SseStream $sseStream) {}

    public function __invoke(Request $request): StreamedResponse
    {
        $lastEventId = $request->header('Last-Event-ID');
        $cursor = is_numeric($lastEventId) ? (int) $lastEventId : null;

        return $this->sseStream->response($request->user(), $cursor);
    }
}
