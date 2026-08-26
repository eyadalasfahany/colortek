<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/** @mixin DatabaseNotification */
final class NotificationResource extends JsonResource
{
    public function toArray($r): array
    {
        $d = $this->data;
        $loc = ($r->user()->locale ?? null) ?: app()->getLocale();

        return ['id' => $this->id, 'type' => $d['type'] ?? $this->type, 'message' => $loc === 'ar' ? ($d['message_ar'] ?? $d['message_en'] ?? '') : ($d['message_en'] ?? ''), 'project_id' => $d['project_id'] ?? null, 'project_reference' => $d['project_reference'] ?? null, 'link' => $d['link'] ?? null, 'link_params' => $d['link_params'] ?? null, 'read_at' => $this->read_at?->toIso8601String(), 'created_at' => $this->created_at?->toIso8601String()];
    }
}
