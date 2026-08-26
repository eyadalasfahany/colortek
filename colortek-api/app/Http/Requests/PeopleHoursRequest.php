<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PeopleHoursRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
        ];
    }
}
