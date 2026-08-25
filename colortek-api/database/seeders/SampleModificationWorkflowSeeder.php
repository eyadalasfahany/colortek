<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Department;
use App\Models\WorkflowTaskDefinition;
use App\Models\WorkflowTemplate;
use App\Models\WorkflowTransition;
use Illuminate\Database\Seeder;

final class SampleModificationWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $sales = Department::query()->where('code', 'sales')->firstOrFail();

        $template = WorkflowTemplate::updateOrCreate(
            ['code' => 'sample_modification', 'version' => 1],
            [
                'name_en' => 'Sample modification',
                'name_ar' => 'تعديل العينة',
                'scope' => 'sample',
                'is_active' => true,
                'published_at' => now(),
            ],
        );

        $definition = WorkflowTaskDefinition::updateOrCreate(
            ['template_id' => $template->id, 'code' => 'sales_create_modification_request'],
            [
                'title_en' => 'Create modification request',
                'title_ar' => 'إنشاء طلب تعديل',
                'instructions_en' => 'Record what the client wants changed and start a new attempt.',
                'instructions_ar' => 'سجل ما يريده العميل تغييره وابدأ محاولة جديدة.',
                'department_id' => $sales->id,
                'is_entry_point' => true,
                'sla_minutes' => null,
                'escalate_after_minutes' => null,
                'required_fields' => ['modification_reason', 'color'],
                'required_attachment_types' => [],
                'form_schema' => [
                    'fields' => [
                        ['key' => 'modification_reason', 'type' => 'textarea', 'label_en' => 'Modification reason', 'label_ar' => 'سبب التعديل', 'required' => true],
                        ['key' => 'color', 'type' => 'text', 'label_en' => 'Colour', 'label_ar' => 'اللون', 'required' => true],
                        ['key' => 'texture', 'type' => 'text', 'label_en' => 'Texture', 'label_ar' => 'الملمس', 'required' => false],
                        ['key' => 'client_reference', 'type' => 'text', 'label_en' => 'Client reference', 'label_ar' => 'مرجع العميل', 'required' => false],
                        ['key' => 'size', 'type' => 'text', 'label_en' => 'Size', 'label_ar' => 'الحجم', 'required' => false],
                        ['key' => 'finish_requirement', 'type' => 'textarea', 'label_en' => 'Finish requirement', 'label_ar' => 'متطلبات التشطيب', 'required' => false],
                        ['key' => 'needed_by', 'type' => 'date', 'label_en' => 'Needed by', 'label_ar' => 'مطلوب قبل', 'required' => false],
                    ],
                ],
                'requires_timer' => false,
                'blocks_when_site_not_ready' => false,
            ],
        );

        WorkflowTransition::query()->where('template_id', $template->id)->delete();

        WorkflowTransition::create([
            'template_id' => $template->id,
            'from_task_definition_id' => null,
            'to_task_definition_id' => $definition->id,
            'condition' => null,
            'join_mode' => 'all',
            'sort_order' => 0,
        ]);
    }
}
