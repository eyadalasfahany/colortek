<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ProjectStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reference' => ['nullable', 'string', 'max:60', 'unique:projects,reference'],
            'name' => ['required', 'string', 'max:200'],
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'quotation_id' => ['nullable', 'integer', 'exists:quotations,id'],
            'sales_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
