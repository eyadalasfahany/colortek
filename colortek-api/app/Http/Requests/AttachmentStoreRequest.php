<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AttachmentStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:10240'],
            'type' => ['required', 'string', Rule::in(['payment_proof', 'general', 'formula_sheet', 'client_approval_form', 'site_report_signed', 'site_photo', 'sample_photo'])],
            'caption' => ['nullable', 'string', 'max:255'],
        ];
    }
}
