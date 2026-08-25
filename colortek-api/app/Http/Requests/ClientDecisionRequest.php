<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ClientDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'in:approved,rejected'],
            'client_signatory_name' => ['required', 'string', 'max:150'],
            'decided_at' => ['required', 'date'],
            'comments' => ['nullable', 'string'],
            'attachment_ids' => ['nullable', 'array'],
        ];
    }
}
