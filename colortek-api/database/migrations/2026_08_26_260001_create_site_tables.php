<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_checklist_items', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('label_en', 250);
            $table->string('label_ar', 250);
            $table->string('answer_type', 20);
            $table->string('unit', 20)->nullable();
            $table->boolean('is_readiness_critical')->default(false);
            $table->boolean('allows_note')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('site_visits', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 60)->unique();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('visit_number')->default(1);
            $table->foreignId('parent_visit_id')->nullable()->constrained('site_visits')->nullOnDelete();
            $table->foreignId('engineer_user_id')->constrained('users');
            $table->string('project_name_on_form', 200);
            $table->string('address_on_form', 250)->nullable();
            $table->string('quotation_number_on_form', 50)->nullable();
            $table->string('client_reference_note', 60)->nullable();
            $table->date('visited_on');
            $table->string('readiness', 20)->default('pending');
            $table->text('general_notes')->nullable();
            $table->string('client_signatory_name', 150)->nullable();
            $table->timestamp('engineer_signed_at')->nullable();
            $table->timestamp('client_signed_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('site_visit_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('checklist_item_id')->constrained('site_checklist_items')->cascadeOnDelete();
            $table->json('answer_value')->nullable();
            $table->boolean('passed')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['site_visit_id', 'checklist_item_id']);
        });

        Schema::create('site_measurements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_visit_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('page_number')->default(1);
            $table->unsignedSmallInteger('line_number');
            $table->string('element_name', 200)->nullable();
            $table->foreignId('element_group_id')->nullable()->constrained('site_measurements')->nullOnDelete();
            $table->decimal('height_m', 8, 3)->nullable();
            $table->decimal('length_m', 8, 3)->nullable();
            $table->decimal('width_m', 8, 3)->nullable();
            $table->decimal('thickness_m', 8, 3)->nullable();
            $table->decimal('diameter_m', 8, 3)->nullable();
            $table->string('other_note', 250)->nullable();
            $table->decimal('area_sqm', 10, 3)->nullable();
            $table->boolean('verified')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('site_measurement_deductions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('measurement_id')->constrained('site_measurements')->cascadeOnDelete();
            $table->string('kind', 40)->default('other');
            $table->string('label', 120)->nullable();
            $table->unsignedSmallInteger('count')->default(1);
            $table->decimal('length_m', 8, 3)->nullable();
            $table->decimal('width_m', 8, 3)->nullable();
            $table->string('sign', 10)->default('subtract');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('corrective_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('checklist_item_id')->nullable()->constrained('site_checklist_items')->nullOnDelete();
            $table->text('description');
            $table->string('responsible_party', 20);
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->string('status', 20)->default('open');
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corrective_actions');
        Schema::dropIfExists('site_measurement_deductions');
        Schema::dropIfExists('site_measurements');
        Schema::dropIfExists('site_visit_answers');
        Schema::dropIfExists('site_visits');
        Schema::dropIfExists('site_checklist_items');
    }
};
