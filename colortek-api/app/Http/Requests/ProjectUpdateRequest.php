<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'stage' => ['sometimes', 'string', 'max:30'],
            'status' => ['sometimes', 'string', 'max:30'],
            'block_all_when_site_not_ready' => ['sometimes', 'boolean'],
        ];
    }
}
