<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ResponsibleParty;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CorrectiveActionStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'site_visit_id' => ['required', 'integer', 'exists:site_visits,id'],
            'description' => ['required', 'string', 'max:2000'],
            'responsible_party' => ['required', Rule::enum(ResponsibleParty::class)],
            'checklist_item_id' => ['nullable', 'integer', 'exists:site_checklist_items,id'],
            // Only meaningful when the party is `colortek`; otherwise the action
            // is routed to Sales regardless.
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
        ];
    }
}
