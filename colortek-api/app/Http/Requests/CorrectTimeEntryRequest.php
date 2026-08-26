<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CorrectTimeEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'started_at' => ['nullable', 'date'],
            'ended_at' => ['nullable', 'date', 'after:started_at'],
            'seconds' => ['nullable', 'integer', 'min:0'],
            'note' => ['required', 'string', 'max:2000'],
        ];
    }
}
