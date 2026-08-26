<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['work_start' => ['sometimes', 'date_format:H:i'], 'work_end' => ['sometimes', 'date_format:H:i'], 'weekend_days' => ['sometimes', 'array'], 'weekend_days.*' => ['string', Rule::in(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])], 'humidity_max' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'], 'sample_repeat_attempt_threshold' => ['sometimes', 'integer', 'min:1'], 'block_all_when_site_not_ready' => ['sometimes', 'boolean'], 'default_locale' => ['sometimes', Rule::in(['en', 'ar'])], 'confirm' => ['sometimes', 'boolean']];
    }
}
