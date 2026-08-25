<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class TaskBlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'blocker_category_id' => ['required', 'integer', 'exists:blocker_categories,id'],
            'reason' => ['required', 'string', 'max:2000'],
            'expected_resolution' => ['nullable', 'date'],
        ];
    }
}
