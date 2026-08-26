<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\Setting;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Setting */ final class SettingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray($r): array
    {
        return ['key' => $this->key, 'value' => $this->value, 'group' => $this->group];
    }
}
