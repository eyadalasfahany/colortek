<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class StoreWorkflowTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // A code identifies the workflow across versions, so it must be new.
            'code' => ['required', 'string', 'max:60', 'unique:workflow_templates,code'],
            'name_en' => ['required', 'string', 'max:150'],
            'name_ar' => ['required', 'string', 'max:150'],
            'scope' => ['required', 'string', 'max:60'],
        ];
    }
}
