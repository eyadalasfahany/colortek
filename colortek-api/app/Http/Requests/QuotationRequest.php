<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\QuotationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class QuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $id = $this->route('id');
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            // The number is typed by Sales; it is the shared key with Odoo.
            // specs/13 §2.
            'number' => [
                $required,
                'string',
                'max:60',
                $id === null
                    ? 'unique:quotations,number'
                    : "unique:quotations,number,{$id},id",
            ],
            'client_id' => [$required, 'integer', 'exists:clients,id'],
            'total_value' => [$required, 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'status' => ['sometimes', Rule::enum(QuotationStatus::class)],
            'odoo_quotation_id' => ['nullable', 'string', 'max:60'],
        ];
    }
}
