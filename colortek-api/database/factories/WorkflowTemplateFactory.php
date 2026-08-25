<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Department;
use App\Models\WorkflowTaskDefinition;
use App\Models\WorkflowTemplate;
use App\Models\WorkflowTransition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowTemplate>
 */
class WorkflowTemplateFactory extends Factory
{
    protected $model = WorkflowTemplate::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify('wf_????'),
            'version' => 1,
            'name_en' => fake()->words(3, true),
            'name_ar' => fake()->words(3, true),
            'scope' => 'project',
            'is_active' => true,
            'published_at' => now(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes): array => [
            'published_at' => null,
            'is_active' => false,
        ]);
    }

    public function twoStep(): static
    {
        return $this->state(fn (array $attributes): array => [
            'code' => 'test_two_step',
        ])->afterCreating(function (WorkflowTemplate $template): void {
            $firstDept = Department::query()->where('code', 'sales')->first()
                ?? Department::factory()->create(['code' => 'sales', 'name' => ['en' => 'Sales', 'ar' => 'المبيعات']]);
            $secondDept = Department::query()->where('code', 'reception')->first()
                ?? Department::factory()->create(['code' => 'reception', 'name' => ['en' => 'Reception', 'ar' => 'الاستقبال']]);

            $first = WorkflowTaskDefinition::create([
                'template_id' => $template->id,
                'code' => 'step_one',
                'title_en' => 'Step One',
                'title_ar' => 'الخطوة الأولى',
                'department_id' => $firstDept->id,
                'is_entry_point' => true,
                'required_fields' => [],
                'required_attachment_types' => [],
            ]);

            $second = WorkflowTaskDefinition::create([
                'template_id' => $template->id,
                'code' => 'step_two',
                'title_en' => 'Step Two',
                'title_ar' => 'الخطوة الثانية',
                'department_id' => $secondDept->id,
                'is_entry_point' => false,
                'required_fields' => [],
                'required_attachment_types' => [],
            ]);

            WorkflowTransition::create([
                'template_id' => $template->id,
                'from_task_definition_id' => $first->id,
                'to_task_definition_id' => $second->id,
                'join_mode' => 'all',
                'sort_order' => 1,
            ]);
        });
    }

    public function threeStep(): static
    {
        return $this->state(fn (array $attributes): array => [
            'code' => 'test_three_step',
        ])->afterCreating(function (WorkflowTemplate $template): void {
            $departments = [
                'sales' => Department::query()->firstOrCreate(
                    ['code' => 'sales'],
                    ['name' => ['en' => 'Sales', 'ar' => 'المبيعات'], 'is_queue' => true, 'active' => true],
                ),
                'reception' => Department::query()->firstOrCreate(
                    ['code' => 'reception'],
                    ['name' => ['en' => 'Reception', 'ar' => 'الاستقبال'], 'is_queue' => true, 'active' => true],
                ),
                'accounting' => Department::query()->firstOrCreate(
                    ['code' => 'accounting'],
                    ['name' => ['en' => 'Accounting', 'ar' => 'المحاسبة'], 'is_queue' => true, 'active' => true],
                ),
            ];

            $steps = [];
            foreach (['sales' => 'sales_step', 'reception' => 'reception_step', 'accounting' => 'accounting_step'] as $deptCode => $stepCode) {
                $steps[$stepCode] = WorkflowTaskDefinition::create([
                    'template_id' => $template->id,
                    'code' => $stepCode,
                    'title_en' => ucfirst($deptCode).' Step',
                    'title_ar' => $deptCode,
                    'department_id' => $departments[$deptCode]->id,
                    'is_entry_point' => $stepCode === 'sales_step',
                    'sla_minutes' => 120,
                    'required_fields' => [],
                    'required_attachment_types' => [],
                ]);
            }

            WorkflowTransition::create([
                'template_id' => $template->id,
                'from_task_definition_id' => $steps['sales_step']->id,
                'to_task_definition_id' => $steps['reception_step']->id,
                'join_mode' => 'all',
                'sort_order' => 1,
            ]);

            WorkflowTransition::create([
                'template_id' => $template->id,
                'from_task_definition_id' => $steps['reception_step']->id,
                'to_task_definition_id' => $steps['accounting_step']->id,
                'join_mode' => 'all',
                'sort_order' => 1,
            ]);
        });
    }
}
