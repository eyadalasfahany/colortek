<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateStream
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() !== null) {
            return $next($request);
        }

        foreach ([$request->bearerToken(), $request->query('token')] as $token) {
            if (! is_string($token) || $token === '') {
                continue;
            }

            $accessToken = PersonalAccessToken::findToken($token);

            if ($accessToken !== null) {
                $request->setUserResolver(fn () => $accessToken->tokenable);

                return $next($request);
            }
        }

        return response()->json(['message' => 'Unauthenticated.'], 401);
    }
}
