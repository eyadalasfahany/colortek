<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ModificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'modification_reason' => ['required', 'string'],
            'color' => ['required', 'string', 'max:255'],
            'texture' => ['nullable', 'string', 'max:255'],
            'client_reference' => ['nullable', 'string', 'max:255'],
            'size' => ['nullable', 'string', 'max:255'],
            'finish_requirement' => ['nullable', 'string'],
            'needed_by' => ['nullable', 'date'],
        ];
    }
}
