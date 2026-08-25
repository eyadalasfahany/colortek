<?php
declare(strict_types=1);
namespace Database\Seeders;
use App\Models\Department;
use App\Models\WorkflowTaskDefinition;
use App\Models\WorkflowTemplate;
use App\Models\WorkflowTransition;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
final class SiteVisitWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $departments = Department::query()->whereIn('code', ['site', 'sales', 'workshop'])->get()->keyBy('code');
        $template = WorkflowTemplate::updateOrCreate(['code' => 'site_visit', 'version' => 1], [
            'name_en' => 'Site visit', 'name_ar' => 'زيارة الموقع', 'scope' => 'site_visit', 'is_active' => true, 'published_at' => now(),
        ]);
        $definitions = [];
        foreach ([
            'site_conduct_visit' => ['site', true, 1440, false],
            'site_set_readiness' => ['site', false, 240, false],
            'corrective_action_task' => ['sales', false, 960, false],
            'site_reinspection' => ['site', false, 960, false],
            'site_execution_work' => ['site', false, null, true],
            'workshop_preparation' => ['workshop', false, null, false],
        ] as $code => [$dept, $entry, $sla, $blocks]) {
            $definitions[$code] = WorkflowTaskDefinition::updateOrCreate(['template_id' => $template->id, 'code' => $code], [
                'title_en' => $code, 'title_ar' => $code, 'instructions_en' => $code, 'instructions_ar' => $code,
                'department_id' => $departments[$dept]->id, 'is_entry_point' => $entry, 'sla_minutes' => $sla,
                'escalate_after_minutes' => $sla ? $sla * 2 : null, 'required_fields' => $code === 'site_set_readiness' ? ['readiness'] : ($code === 'corrective_action_task' ? ['resolution_note'] : []),
                'required_attachment_types' => [], 'form_schema' => ['fields' => []], 'blocks_when_site_not_ready' => $blocks,
            ]);
        }
        WorkflowTransition::query()->where('template_id', $template->id)->delete();
        WorkflowTransition::create([
            'template_id' => $template->id,
            'from_task_definition_id' => $definitions['site_conduct_visit']->id,
            'to_task_definition_id' => $definitions['site_set_readiness']->id,
            'join_mode' => 'any', 'sort_order' => 1,
        ]);
    }
}
