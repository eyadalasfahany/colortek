<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Permission\Models\Role;

/** @mixin Role */ final class RoleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray($r): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'permissions_count' => $this->permissions_count ?? 0, 'users_count' => $this->users_count ?? 0, 'permissions' => $this->whenLoaded('permissions', fn () => $this->permissions->pluck('name')), 'is_protected' => $this->name === 'super_admin'];
    }
}
