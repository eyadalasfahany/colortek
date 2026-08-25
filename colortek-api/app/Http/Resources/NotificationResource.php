<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/** @mixin DatabaseNotification */
final class NotificationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $d = is_array($this->data) ? $this->data : [];
        $loc = $request->user()?->locale ?? app()->getLocale();

        return ['id' => $this->id, 'type' => $d['type'] ?? $this->type, 'message' => $loc === 'ar' ? ($d['message_ar'] ?? $d['message_en'] ?? '') : ($d['message_en'] ?? ''), 'project_id' => $d['project_id'] ?? null, 'project_reference' => $d['project_reference'] ?? null, 'link' => $d['link'] ?? null, 'link_params' => $d['link_params'] ?? null, 'read_at' => $this->read_at?->toIso8601String(), 'created_at' => $this->created_at?->toIso8601String()];
    }
}
