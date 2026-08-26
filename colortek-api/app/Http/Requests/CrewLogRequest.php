<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CrewLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'log_date' => ['nullable', 'date'],
            'task_id' => ['nullable', 'integer', 'exists:tasks,id'],
            'work_done' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'string'],
            'weather_note' => ['nullable', 'string', 'max:120'],
            'issues' => ['nullable', 'string'],
            'members' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'array', 'min:1'],
            'members.*.employee_id' => ['required', 'integer', 'exists:employees,id'],
            'members.*.hours' => ['required', 'numeric', 'min:0.25', 'max:24'],
            'members.*.role_note' => ['nullable', 'string', 'max:120'],
        ];
    }
}
