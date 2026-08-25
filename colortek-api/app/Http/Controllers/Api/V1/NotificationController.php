<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;

final class NotificationController extends Controller
{
    public function index($r)
    {
        return NotificationResource::collection($r->user()->notifications()->latest()->paginate($r->integer('per_page', 20)))->response();
    }

    public function unreadCount($r)
    {
        return response()->json(['data' => ['count' => $r->user()->unreadNotifications()->count()]]);
    }

    public function markRead($r, $id)
    {
        $n = $r->user()->notifications()->whereKey($id)->firstOrFail();
        $n->markAsRead();

        return response()->json(['data' => NotificationResource::make($n->fresh())]);
    }

    public function markAllRead($r)
    {
        $r->user()->unreadNotifications->markAsRead();

        return response()->json(['data' => null]);
    }
}
