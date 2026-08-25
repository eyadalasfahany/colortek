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
    private function seedDefinitions(WorkflowTemplate $template, $departments): array
    {
        $configs = [
            'sales_create_sample_request' => [
                'title_en' => 'Create sample request',
                'title_ar' => 'إنشاء طلب عينة',
                'instructions_en' => 'Record the client sample requirement.',
                'instructions_ar' => 'سجل متطلبات عينة العميل.',
                'department' => 'sales',
                'is_entry_point' => true,
                'sla_minutes' => null,
                'escalate_after_minutes' => null,
                'required_fields' => ['client_id', 'color'],
                'required_attachment_types' => [],
                'requires_timer' => false,
                'blocks_when_site_not_ready' => false,
                'form_schema' => [
                    'fields' => [
                        ['key' => 'client_id', 'type' => 'select', 'label_en' => 'Client', 'label_ar' => 'العميل', 'required' => true],
                        ['key' => 'project_id', 'type' => 'select', 'label_en' => 'Project', 'label_ar' => 'المشروع', 'required' => false],
                        ['key' => 'color', 'type' => 'text', 'label_en' => 'Colour', 'label_ar' => 'اللون', 'required' => true],
                        ['key' => 'texture', 'type' => 'text', 'label_en' => 'Texture', 'label_ar' => 'الملمس', 'required' => false],
                        ['key' => 'client_reference', 'type' => 'text', 'label_en' => 'Client reference', 'label_ar' => 'مرجع العميل', 'required' => false],
                        ['key' => 'size', 'type' => 'text', 'label_en' => 'Size', 'label_ar' => 'الحجم', 'required' => false],
                        ['key' => 'finish_requirement', 'type' => 'textarea', 'label_en' => 'Finish requirement', 'label_ar' => 'متطلبات التشطيب', 'required' => false],
                        ['key' => 'needed_by', 'type' => 'date', 'label_en' => 'Needed by', 'label_ar' => 'مطلوب قبل', 'required' => false],
                        ['key' => 'notes', 'type' => 'textarea', 'label_en' => 'Notes', 'label_ar' => 'ملاحظات', 'required' => false],
                    ],
                ],
            ],
            'reception_review_sample_request' => [
                'title_en' => 'Review sample request',
                'title_ar' => 'مراجعة طلب العينة',
                'instructions_en' => 'Check the request is complete and forward to approval.',
                'instructions_ar' => 'تحقق من اكتمال الطلب وأعده للموافقة.',
                'department' => 'reception',
                'is_entry_point' => false,
                'sla_minutes' => 240,
                'escalate_after_minutes' => 480,
                'required_fields' => ['review_result'],
                'required_attachment_types' => [],
                'requires_timer' => false,
                'blocks_when_site_not_ready' => false,
                'form_schema' => [
                    'fields' => [
                        ['key' => 'review_result', 'type' => 'select', 'options' => ['forward', 'return_to_sales'], 'label_en' => 'Result', 'label_ar' => 'النتيجة', 'required' => true],
                        ['key' => 'note', 'type' => 'textarea', 'label_en' => 'Note', 'label_ar' => 'ملاحظة', 'required' => false],
                    ],
                ],
            ],
            'sales_fix_sample_request' => [
                'title_en' => 'Fix sample request',
                'title_ar' => 'تصحيح طلب العينة',
                'instructions_en' => 'Reception returned this request. Fix and resubmit.',
                'instructions_ar' => 'أعاد الاستقبال الطلب. صحح وأعد الإرسال.',
                'department' => 'sales',
                'is_entry_point' => false,
                'sla_minutes' => 240,
                'escalate_after_minutes' => 480,
                'required_fields' => ['color'],
                'required_attachment_types' => [],
                'requires_timer' => false,
                'blocks_when_site_not_ready' => false,
                'form_schema' => [
                    'fields' => [
                        ['key' => 'color', 'type' => 'text', 'label_en' => 'Colour', 'label_ar' => 'اللون', 'required' => true],
                        ['key' => 'notes', 'type' => 'textarea', 'label_en' => 'Notes', 'label_ar' => 'ملاحظات', 'required' => false],
                    ],
                ],
            ],
            'manager_approve_sample' => [
                'title_en' => 'Manager approval',
                'title_ar' => 'موافقة الإدارة',
                'instructions_en' => 'Approve or reject the sample request.',
                'instructions_ar' => 'وافق أو ارفض طلب العينة.',
                'department' => 'management',
                'is_entry_point' => false,
                'sla_minutes' => 240,
                'escalate_after_minutes' => 480,
                'required_fields' => ['decision'],
                'required_attachment_types' => [],
                'requires_timer' => false,
                'blocks_when_site_not_ready' => false,
                'form_schema' => [
                    'fields' => [
                        ['key' => 'decision', 'type' => 'select', 'options' => ['approved', 'rejected'], 'label_en' => 'Decision', 'label_ar' => 'القرار', 'required' => true],
                        ['key' => 'comments', 'type' => 'textarea', 'label_en' => 'Comments', 'label_ar' => 'ملاحظات', 'required' => false],
                    ],
                ],
            ],
            'sales_sample_rejected' => [
                'title_en' => 'Sample rejected',
                'title_ar' => 'رفض العينة',
                'instructions_en' => 'Manager rejected this sample request.',
                'instructions_ar' => 'رفضت الإدارة طلب العينة.',
                'department' => 'sales',
                'is_entry_point' => false,
                'sla_minutes' => null,
                'escalate_after_minutes' => null,
                'required_fields' => [],
                'required_attachment_types' => [],
                'requires_timer' => false,
                'blocks_when_site_not_ready' => false,
                'form_schema' => ['fields' => []],
            ],
            'workshop_make_sample' => [
                'title_en' => 'Make sample',
                'title_ar' => 'تصنيع العينة',
                'instructions_en' => 'Make the physical sample and photograph it.',
                'instructions_ar' => 'صنع العينة الفعلية وصورها.',
                'department' => 'workshop',
                'is_entry_point' => false,
                'sla_minutes' => 1440,
                'escalate_after_minutes' => 2400,
                'required_fields' => ['ready_for_registration'],
                'required_attachment_types' => ['sample_photo'],
                'requires_timer' => true,
                'blocks_when_site_not_ready' => false,
                'form_schema' => [
                    'fields' => [
                        ['key' => 'finish_note', 'type' => 'textarea', 'label_en' => 'Finish note', 'label_ar' => 'ملاحظة التشطيب', 'required' => false],
                        ['key' => 'ready_for_registration', 'type' => 'boolean', 'label_en' => 'Ready for registration', 'label_ar' => 'جاهز للتسجيل', 'required' => true],
                    ],
                ],
            ],
            'tinting_author_formula' => [
                'title_en' => 'Author formula',
                'title_ar' => 'كتابة التركيبة',
                'instructions_en' => 'Author the tinting formula for this sample.',
                'instructions_ar' => 'اكتب تركيبة التلوين لهذه العينة.',
                'department' => 'tinting',
                'is_entry_point' => false,
                'sla_minutes' => 480,
                'escalate_after_minutes' => 960,
                'required_fields' => ['author_employee_id', 'authored_at'],
                'required_attachment_types' => [],
                'requires_timer' => true,
                'blocks_when_site_not_ready' => false,
                'form_schema' => [
                    'fields' => [
                        ['key' => 'body', 'type' => 'textarea', 'label_en' => 'Formula text', 'label_ar' => 'نص التركيبة', 'required' => false],
                        ['key' => 'author_employee_id', 'type' => 'employee', 'label_en' => 'Author', 'label_ar' => 'الكاتب', 'required' => true],
                        ['key' => 'authored_at', 'type' => 'date', 'label_en' => 'Authored on', 'label_ar' => 'تاريخ الكتابة', 'required' => true],
                    ],
                ],
            ],
            'reception_register_formula' => [
                'title_en' => 'Register formula',
                'title_ar' => 'تسجيل التركيبة',
                'instructions_en' => 'Register the authored formula against the scanned sheet.',
                'instructions_ar' => 'سجل التركيبة المكتوبة مقابل الورقة الممسوحة.',
                'department' => 'reception',
                'is_entry_point' => false,
                'sla_minutes' => 240,
                'escalate_after_minutes' => 480,
                'required_fields' => ['confirm_matches_sheet'],
                'required_attachment_types' => [],
                'requires_timer' => false,
                'blocks_when_site_not_ready' => false,
                'form_schema' => [
                    'fields' => [
                        ['key' => 'confirm_matches_sheet', 'type' => 'boolean', 'label_en' => 'Matches sheet', 'label_ar' => 'يطابق الورقة', 'required' => true],
                        ['key' => 'corrections', 'type' => 'textarea', 'label_en' => 'Corrections', 'label_ar' => 'تصحيحات', 'required' => false],
                    ],
                ],
            ],
            'sales_get_client_decision' => [
                'title_en' => 'Client decision',
                'title_ar' => 'قرار العميل',
                'instructions_en' => 'Print the approval form, get it signed, and record the decision.',
                'instructions_ar' => 'اطبع نموذج الموافقة، احصل على التوقيع، وسجل القرار.',
                'department' => 'sales',
                'is_entry_point' => false,
                'sla_minutes' => 2400,
                'escalate_after_minutes' => 4800,
                'required_fields' => ['decision', 'client_signatory_name', 'decided_at'],
                'required_attachment_types' => ['client_approval_form'],
                'requires_timer' => false,
                'blocks_when_site_not_ready' => false,
                'form_schema' => [
                    'fields' => [
                        ['key' => 'decision', 'type' => 'select', 'options' => ['approved', 'rejected'], 'label_en' => 'Decision', 'label_ar' => 'القرار', 'required' => true],
                        ['key' => 'client_signatory_name', 'type' => 'text', 'label_en' => 'Signatory', 'label_ar' => 'الموقع', 'required' => true],
                        ['key' => 'decided_at', 'type' => 'date', 'label_en' => 'Date on form', 'label_ar' => 'التاريخ على النموذج', 'required' => true],
                        ['key' => 'comments', 'type' => 'textarea', 'label_en' => 'Comments', 'label_ar' => 'ملاحظات', 'required' => false],
                    ],
                ],
            ],
        ];

        $definitions = [];
        foreach ($configs as $code => $config) {
            $definitions[$code] = WorkflowTaskDefinition::updateOrCreate(
                ['template_id' => $template->id, 'code' => $code],
                [
                    'title_en' => $config['title_en'],
                    'title_ar' => $config['title_ar'],
                    'instructions_en' => $config['instructions_en'],
                    'instructions_ar' => $config['instructions_ar'],
                    'department_id' => $departments[$config['department']]->id,
                    'is_entry_point' => $config['is_entry_point'],
                    'sla_minutes' => $config['sla_minutes'],
                    'escalate_after_minutes' => $config['escalate_after_minutes'],
                    'required_fields' => $config['required_fields'],
                    'required_attachment_types' => $config['required_attachment_types'],
                    'form_schema' => $config['form_schema'],
                    'requires_timer' => $config['requires_timer'],
                    'blocks_when_site_not_ready' => $config['blocks_when_site_not_ready'],
                ],
            );
        }

        return $definitions;
    }

    /** @param array<string, WorkflowTaskDefinition> $definitions */
    private function seedTransitions(WorkflowTemplate $template, array $definitions): void
    {
        WorkflowTransition::query()->where('template_id', $template->id)->delete();

        $transitions = [
            ['from' => null, 'to' => 'sales_create_sample_request', 'condition' => null, 'join_mode' => 'all', 'sort_order' => 0],
            ['from' => 'sales_create_sample_request', 'to' => 'reception_review_sample_request', 'condition' => null, 'join_mode' => 'any', 'sort_order' => 1],
            ['from' => 'reception_review_sample_request', 'to' => 'manager_approve_sample', 'condition' => ['field' => 'review_result', 'operator' => 'equals', 'value' => 'forward'], 'join_mode' => 'any', 'sort_order' => 1],
            ['from' => 'reception_review_sample_request', 'to' => 'sales_fix_sample_request', 'condition' => ['field' => 'review_result', 'operator' => 'equals', 'value' => 'return_to_sales'], 'join_mode' => 'any', 'sort_order' => 2],
            ['from' => 'sales_fix_sample_request', 'to' => 'reception_review_sample_request', 'condition' => null, 'join_mode' => 'any', 'sort_order' => 1],
            ['from' => 'manager_approve_sample', 'to' => 'workshop_make_sample', 'condition' => ['field' => 'decision', 'operator' => 'equals', 'value' => 'approved'], 'join_mode' => 'any', 'sort_order' => 1],
            ['from' => 'manager_approve_sample', 'to' => 'tinting_author_formula', 'condition' => ['field' => 'decision', 'operator' => 'equals', 'value' => 'approved'], 'join_mode' => 'any', 'sort_order' => 2],
            ['from' => 'manager_approve_sample', 'to' => 'sales_sample_rejected', 'condition' => ['field' => 'decision', 'operator' => 'equals', 'value' => 'rejected'], 'join_mode' => 'any', 'sort_order' => 3],
            ['from' => 'workshop_make_sample', 'to' => 'reception_register_formula', 'condition' => null, 'join_mode' => 'all', 'sort_order' => 1],
            ['from' => 'tinting_author_formula', 'to' => 'reception_register_formula', 'condition' => null, 'join_mode' => 'all', 'sort_order' => 1],
            ['from' => 'reception_register_formula', 'to' => 'sales_get_client_decision', 'condition' => null, 'join_mode' => 'any', 'sort_order' => 1],
        ];

        foreach ($transitions as $transition) {
            WorkflowTransition::create([
                'template_id' => $template->id,
                'from_task_definition_id' => $transition['from'] === null
                    ? null
                    : $definitions[$transition['from']]->id,
                'to_task_definition_id' => $definitions[$transition['to']]->id,
                'condition' => $transition['condition'],
                'join_mode' => $transition['join_mode'],
                'sort_order' => $transition['sort_order'],
            ]);
        }
    }
}
