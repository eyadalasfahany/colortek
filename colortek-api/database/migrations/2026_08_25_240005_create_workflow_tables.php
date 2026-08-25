<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50);
            $table->unsignedInteger('version');
            $table->string('name_en', 150);
            $table->string('name_ar', 150);
            $table->enum('scope', ['project', 'sample', 'payment', 'site_visit'])->default('project');
            $table->boolean('is_active')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['code', 'version']);
        });

        Schema::create('workflow_task_definitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('template_id')->constrained('workflow_templates')->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('title_en', 200);
            $table->string('title_ar', 200);
            $table->text('instructions_en')->nullable();
            $table->text('instructions_ar')->nullable();
            $table->foreignId('department_id')->constrained('departments');
            $table->boolean('is_entry_point')->default(false);
            $table->unsignedInteger('sla_minutes')->nullable();
            $table->unsignedInteger('escalate_after_minutes')->nullable();
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->boolean('requires_timer')->default(false);
            $table->json('required_fields')->nullable();
            $table->json('required_attachment_types')->nullable();
            $table->json('form_schema')->nullable();
            $table->boolean('blocks_when_site_not_ready')->default(false);
            $table->json('auto_complete_rule')->nullable();
            $table->timestamps();

            $table->unique(['template_id', 'code']);
        });

        Schema::create('workflow_transitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('template_id')->constrained('workflow_templates')->cascadeOnDelete();
            $table->foreignId('from_task_definition_id')->nullable()->constrained('workflow_task_definitions')->cascadeOnDelete();
            $table->foreignId('to_task_definition_id')->constrained('workflow_task_definitions')->cascadeOnDelete();
            $table->json('condition')->nullable();
            $table->enum('join_mode', ['all', 'any'])->default('all');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('workflow_instances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('template_id')->constrained('workflow_templates');
            $table->nullableMorphs('subject');
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->enum('status', ['running', 'completed', 'cancelled'])->default('running');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('project_id');
        });

        Schema::create('workflow_transition_log', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('instance_id')->constrained('workflow_instances')->cascadeOnDelete();
            $table->foreignId('transition_id')->constrained('workflow_transitions')->cascadeOnDelete();
            $table->foreignId('source_task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->boolean('taken');
            $table->string('reason', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_transition_log');
        Schema::dropIfExists('workflow_instances');
        Schema::dropIfExists('workflow_transitions');
        Schema::dropIfExists('workflow_task_definitions');
        Schema::dropIfExists('workflow_templates');
    }
};
