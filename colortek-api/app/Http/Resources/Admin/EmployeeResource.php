<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\Employee;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Employee */ final class EmployeeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray($r): array
    {
        return ['id' => $this->id, 'code' => $this->code, 'name' => $this->name, 'active' => $this->active, 'department_id' => $this->department_id, 'department' => $this->whenLoaded('department', fn () => ['id' => $this->department->id, 'code' => $this->department->code, 'name' => $this->department->getTranslation('name', 'en')]), 'user_id' => $this->user_id];
    }
}
