<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ProjectStage;
use App\Enums\ProjectStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ProjectUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:200'],
            'sales_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            // Validated against the enums: an unknown value used to be stored
            // and then blow up when ProjectStage::from() ran on the next read.
            'stage' => ['sometimes', Rule::enum(ProjectStage::class)],
            'status' => ['sometimes', Rule::enum(ProjectStatus::class)],
            'block_all_when_site_not_ready' => ['sometimes', 'boolean'],
        ];
    }
}
