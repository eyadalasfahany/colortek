<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class HandleIdempotencyKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');
        if (! is_string($key) || $key === '' || $request->user() === null) {
            return $next($request);
        }

        $fingerprint = $request->method().':'.$request->path();
        $existing = IdempotencyKey::query()
            ->where('user_id', $request->user()->id)
            ->where('key', $key)
            ->where('route_fingerprint', $fingerprint)
            ->first();

        if ($existing !== null) {
            return response()->json($existing->response_body, $existing->response_code);
        }

        /** @var Response $response */
        $response = $next($request);

        if ($response instanceof JsonResponse && $response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            IdempotencyKey::query()->create([
                'user_id' => $request->user()->id,
                'key' => $key,
                'route_fingerprint' => $fingerprint,
                'response_code' => $response->getStatusCode(),
                'response_body' => $response->getData(true),
            ]);
        }

        return $response;
    }
}
