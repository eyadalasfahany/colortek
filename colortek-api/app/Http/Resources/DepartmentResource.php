<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Department */
final class DepartmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $locale = $request->getPreferredLanguage(['en', 'ar']) ?? app()->getLocale();

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->getTranslation('name', $locale),
            'is_queue' => $this->is_queue,
            'active' => $this->active,
        ];
    }
}
