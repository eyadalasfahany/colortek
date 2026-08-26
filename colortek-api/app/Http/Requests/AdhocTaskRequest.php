<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\TaskPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AdhocTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'instructions' => ['nullable', 'string'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'priority' => ['nullable', Rule::enum(TaskPriority::class)],
            'due_at' => ['nullable', 'date'],
        ];
    }
}
