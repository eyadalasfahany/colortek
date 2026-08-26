<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Department;
use App\Models\WorkflowTaskDefinition;
use App\Models\WorkflowTemplate;
use App\Models\WorkflowTransition;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

final class SampleWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $departments = Department::query()
            ->whereIn('code', ['sales', 'reception', 'management', 'workshop', 'tinting'])
            ->get()
            ->keyBy('code');

        $template = WorkflowTemplate::updateOrCreate(
            ['code' => 'sample_request', 'version' => 1],
            [
                'name_en' => 'Sample request',
                'name_ar' => 'طلب عينة',
                'scope' => 'sample',
                'is_active' => true,
                'published_at' => now(),
            ],
        );

        $definitions = $this->seedDefinitions($template, $departments);
        $this->seedTransitions($template, $definitions);
    }

    /**
     * @param  Collection<string, Department>  $departments
     * @return array<string, WorkflowTaskDefinition>
     */
    private function seedDefinitions(WorkflowTemplate $template, Collection $departments): array
    {
        $configs = [
            'sales_create_sample_request' => ['sales', true, null, null, false, []],
            'reception_review_sample_request' => ['reception', false, 240, 480, false, ['review_result']],
            'sales_fix_sample_request' => ['sales', false, 240, 480, false, ['color']],
            'manager_approve_sample' => ['management', false, 240, 480, false, ['decision']],
            'sales_sample_rejected' => ['sales', false, null, null, false, []],
            'workshop_make_sample' => ['workshop', false, 1440, 2400, true, ['ready_for_registration']],
            'tinting_author_formula' => ['tinting', false, 480, 960, true, ['body', 'author_employee_id']],
            'reception_register_formula' => ['reception', false, 240, 480, false, ['confirm_matches_sheet']],
            'sales_get_client_decision' => ['sales', false, 2400, 4800, false, ['decision', 'client_signatory_name', 'decided_at']],
        ];

        $definitions = [];
        foreach ($configs as $code => [$dept, $entry, $sla, $escalate, $timer, $required]) {
            $attachments = match ($code) {
                'workshop_make_sample' => ['sample_photo'],
                'sales_get_client_decision' => ['client_approval_form'],
                default => [],
            };

            $definitions[$code] = WorkflowTaskDefinition::updateOrCreate(
                ['template_id' => $template->id, 'code' => $code],
                [
                    'title_en' => str_replace('_', ' ', $code),
                    'title_ar' => str_replace('_', ' ', $code),
                    'instructions_en' => str_replace('_', ' ', $code),
                    'instructions_ar' => str_replace('_', ' ', $code),
                    'department_id' => $departments[$dept]->id,
                    'is_entry_point' => $entry,
                    'sla_minutes' => $sla,
                    'escalate_after_minutes' => $escalate,
                    'required_fields' => $required,
                    'required_attachment_types' => $attachments,
                    'form_schema' => ['fields' => $this->fieldsFor($code)],
                    'requires_timer' => $timer,
                    'blocks_when_site_not_ready' => false,
                ],
            );
        }

        return $definitions;
    }

    /** @return list<array<string, mixed>> */
    private function fieldsFor(string $code): array
    {
        return match ($code) {
            'sales_create_sample_request' => [
                ['key' => 'client_id', 'type' => 'number', 'label_en' => 'Client', 'required' => true],
                ['key' => 'project_id', 'type' => 'number', 'label_en' => 'Project', 'required' => false],
                ['key' => 'color', 'type' => 'text', 'label_en' => 'Colour', 'required' => true],
                ['key' => 'texture', 'type' => 'text', 'label_en' => 'Texture', 'required' => false],
                ['key' => 'size', 'type' => 'text', 'label_en' => 'Size', 'required' => false],
            ],
            'reception_review_sample_request' => [
                ['key' => 'review_result', 'type' => 'select', 'options' => ['forward', 'return_to_sales'], 'label_en' => 'Result', 'required' => true],
                ['key' => 'note', 'type' => 'textarea', 'label_en' => 'Note', 'required' => false],
            ],
            'manager_approve_sample' => [
                ['key' => 'decision', 'type' => 'select', 'options' => ['approved', 'rejected'], 'label_en' => 'Decision', 'required' => true],
                ['key' => 'comments', 'type' => 'textarea', 'label_en' => 'Comments', 'required' => false],
            ],
            'workshop_make_sample' => [
                ['key' => 'ready_for_registration', 'type' => 'boolean', 'label_en' => 'Ready for registration', 'required' => true],
            ],
            'tinting_author_formula' => [
                ['key' => 'body', 'type' => 'textarea', 'label_en' => 'Formula body', 'required' => false],
                ['key' => 'author_employee_id', 'type' => 'employee', 'label_en' => 'Author', 'required' => true],
                ['key' => 'authored_at', 'type' => 'date', 'label_en' => 'Authored at', 'required' => false],
            ],
            'reception_register_formula' => [
                ['key' => 'confirm_matches_sheet', 'type' => 'boolean', 'label_en' => 'Matches sheet', 'required' => true],
            ],
            'sales_get_client_decision' => [
                ['key' => 'decision', 'type' => 'select', 'options' => ['approved', 'rejected'], 'label_en' => 'Decision', 'required' => true],
                ['key' => 'client_signatory_name', 'type' => 'text', 'label_en' => 'Signatory', 'required' => true],
                ['key' => 'decided_at', 'type' => 'date', 'label_en' => 'Date on form', 'required' => true],
                ['key' => 'comments', 'type' => 'textarea', 'label_en' => 'Comments', 'required' => false],
            ],
            default => [],
        };
    }

    /** @param array<string, WorkflowTaskDefinition> $definitions */
    private function seedTransitions(WorkflowTemplate $template, array $definitions): void
    {
        WorkflowTransition::query()->where('template_id', $template->id)->delete();

        $rows = [
            [null, 'sales_create_sample_request', null, 'all', 0],
            ['sales_create_sample_request', 'reception_review_sample_request', null, 'any', 1],
            ['reception_review_sample_request', 'manager_approve_sample', ['field' => 'review_result', 'operator' => 'equals', 'value' => 'forward'], 'any', 1],
            ['reception_review_sample_request', 'sales_fix_sample_request', ['field' => 'review_result', 'operator' => 'equals', 'value' => 'return_to_sales'], 'any', 2],
            ['sales_fix_sample_request', 'reception_review_sample_request', null, 'any', 1],
            ['manager_approve_sample', 'workshop_make_sample', ['field' => 'decision', 'operator' => 'equals', 'value' => 'approved'], 'any', 1],
            ['manager_approve_sample', 'tinting_author_formula', ['field' => 'decision', 'operator' => 'equals', 'value' => 'approved'], 'any', 2],
            ['manager_approve_sample', 'sales_sample_rejected', ['field' => 'decision', 'operator' => 'equals', 'value' => 'rejected'], 'any', 3],
            ['workshop_make_sample', 'reception_register_formula', null, 'all', 1],
            ['tinting_author_formula', 'reception_register_formula', null, 'all', 1],
            ['reception_register_formula', 'sales_get_client_decision', null, 'any', 1],
        ];

        foreach ($rows as [$from, $to, $condition, $join, $sort]) {
            WorkflowTransition::create([
                'template_id' => $template->id,
                'from_task_definition_id' => $from === null ? null : $definitions[$from]->id,
                'to_task_definition_id' => $definitions[$to]->id,
                'condition' => $condition,
                'join_mode' => $join,
                'sort_order' => $sort,
            ]);
        }
    }
}
