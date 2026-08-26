<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
final class AdminUserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray($request): array
    {
        /** @var User $user */
        $user = $this->resource;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'locale' => $user->locale,
            'active' => $user->active,
            'last_seen_at' => $user->last_seen_at,
            'roles' => $this->whenLoaded('roles', fn () => $user->roles->pluck('name')),
            'departments' => $this->whenLoaded('departments', fn () => $user->departments->map(
                fn ($department) => [
                    'id' => $department->id,
                    'code' => $department->code,
                    'name' => $department->getTranslation('name', 'en'),
                    'is_supervisor' => (bool) optional($department->getRelationValue('pivot'))->is_supervisor,
                ],
            )),
            'primary_department_id' => $user->primary_department_id,
            'is_super_admin' => $user->isSuperAdmin(),
        ];
    }
}
