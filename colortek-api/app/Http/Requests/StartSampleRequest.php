<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StartSampleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'color' => ['required', 'string', 'max:120'],
            'texture' => ['nullable', 'string', 'max:120'],
            'client_reference' => ['nullable', 'string', 'max:200'],
            'size' => ['nullable', 'string', 'max:60'],
            'finish_requirement' => ['nullable', 'string'],
            'needed_by' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
