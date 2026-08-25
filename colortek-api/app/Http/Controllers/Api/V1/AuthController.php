<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AuthLoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class AuthController extends Controller
{
    public function login(AuthLoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->validated('email'))->first();

        if ($user === null || ! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('These credentials do not match our records.')],
            ]);
        }

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'data' => [
                'token' => $token,
                'user' => UserResource::make($user->load(['departments', 'primaryDepartment'])),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $bearerToken = $request->bearerToken();

        if ($user !== null && $bearerToken !== null && str_contains($bearerToken, '|')) {
            [$tokenId] = explode('|', $bearerToken, 2);
            $user->tokens()->whereKey($tokenId)->delete();
        }

        return response()->json(['data' => null]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['departments', 'primaryDepartment']);

        return response()->json([
            'data' => UserResource::make($user),
            'meta' => ['unread_notifications' => $user->unreadNotifications()->count()],
        ]);
    }
}
