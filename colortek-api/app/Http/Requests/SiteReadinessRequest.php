<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\SiteReadiness;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SiteReadinessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'readiness' => ['required', Rule::enum(SiteReadiness::class)],
            'summary' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
