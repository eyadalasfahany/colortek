<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class TaskCompleteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'fields' => ['sometimes', 'array'],
            'attachment_ids' => ['sometimes', 'array'],
            'attachment_ids.*' => ['integer'],
        ];
    }
}
