<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Department;
use App\Models\WorkflowTaskDefinition;
use App\Models\WorkflowTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowTaskDefinition>
 */
class WorkflowTaskDefinitionFactory extends Factory
{
    protected $model = WorkflowTaskDefinition::class;

    public function definition(): array
    {
        return [
            'template_id' => WorkflowTemplate::factory(),
            'code' => fake()->unique()->lexify('step_????'),
            'title_en' => fake()->sentence(3),
            'title_ar' => fake()->sentence(3),
            'department_id' => Department::factory(),
            'is_entry_point' => false,
            'required_fields' => [],
            'required_attachment_types' => [],
        ];
    }
}
