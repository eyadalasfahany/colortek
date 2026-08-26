<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\HolidayType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class HolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['date' => ['required', 'date'], 'name' => ['required', 'array'], 'name.en' => ['required', 'string', 'max:255'], 'name.ar' => ['nullable', 'string', 'max:255'], 'type' => ['required', Rule::enum(HolidayType::class)], 'is_recurring' => ['sometimes', 'boolean'], 'confirm' => ['sometimes', 'boolean']];
    }
}
