<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CrewLogMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CrewLogMember */
final class CrewLogMemberResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'employee_id' => $this->employee_id,
            'employee_name' => $this->whenLoaded('employee', fn () => $this->employee->name),
            'hours' => (float) $this->hours,
            'role_note' => $this->role_note,
        ];
    }
}
