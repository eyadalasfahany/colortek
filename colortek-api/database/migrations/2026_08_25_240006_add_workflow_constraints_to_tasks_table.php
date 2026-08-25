<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $driver = Schema::getConnection()->getDriverName();
            $expression = $driver === 'sqlite'
                ? "CASE WHEN status IN ('completed','cancelled') THEN 'closed-' || reference ELSE 'open' END"
                : "CASE WHEN status IN ('completed','cancelled') THEN CONCAT('closed-', reference) ELSE 'open' END";

            $table->string('open_marker', 80)->storedAs($expression);
        });

        Schema::table('tasks', function (Blueprint $table): void {
            $table->foreign('instance_id')->references('id')->on('workflow_instances')->nullOnDelete();
            $table->foreign('task_definition_id')->references('id')->on('workflow_task_definitions')->nullOnDelete();
            $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
            $table->unique(['instance_id', 'task_definition_id', 'open_marker'], 'tasks_one_open_per_definition');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropUnique('tasks_one_open_per_definition');
            $table->dropForeign(['instance_id']);
            $table->dropForeign(['task_definition_id']);
            $table->dropForeign(['project_id']);
            $table->dropColumn('open_marker');
        });
    }
};
