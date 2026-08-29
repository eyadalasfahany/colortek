<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ClientRequest extends FormRequest
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
            'name' => [$required, 'string', 'max:200'],
            'contact_person' => ['nullable', 'string', 'max:200'],
            'phone' => ['nullable', 'string', 'max:60'],
            'email' => ['nullable', 'email', 'max:200'],
            'address' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'odoo_client_id' => [
                'nullable',
                'string',
                'max:60',
                $id === null
                    ? 'unique:clients,odoo_client_id'
                    : "unique:clients,odoo_client_id,{$id},id",
            ],
        ];
    }
}
