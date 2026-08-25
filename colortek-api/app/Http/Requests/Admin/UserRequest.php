<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $id = $this->route('id');

        return ['name' => ['sometimes', 'string', 'max:150'], 'email' => ['sometimes', 'email', 'max:190', Rule::unique('users', 'email')->ignore($id)], 'password' => [$id ? 'sometimes' : 'required', 'string', 'min:8'], 'phone' => ['nullable', 'string', 'max:30'], 'locale' => ['sometimes', Rule::in(['en', 'ar'])], 'primary_department_id' => ['nullable', 'integer', 'exists:departments,id'], 'active' => ['sometimes', 'boolean'], 'release_claimed_tasks' => ['sometimes', 'boolean'], 'departments' => ['sometimes', 'array'], 'departments.*.id' => ['required', 'integer', 'exists:departments,id'], 'departments.*.is_supervisor' => ['sometimes', 'boolean']];
    }
}
