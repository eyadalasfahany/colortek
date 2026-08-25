<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 60)->unique();
            $table->unsignedBigInteger('instance_id')->nullable();
            $table->unsignedBigInteger('task_definition_id')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->nullableMorphs('subject');
            $table->string('title', 200);
            $table->text('instructions')->nullable();
            $table->foreignId('department_id')->constrained('departments');
            $table->foreignId('claimed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('claimed_at')->nullable();
            $table->enum('status', [
                'pending',
                'waiting',
                'ready',
                'claimed',
                'in_progress',
                'paused',
                'blocked',
                'completed',
                'cancelled',
            ])->default('ready');
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->timestamp('due_at')->nullable();
            $table->boolean('is_overdue')->default(false);
            $table->timestamp('escalated_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('active_seconds')->default(0);
            $table->unsignedInteger('paused_seconds')->default(0);
            $table->unsignedInteger('blocked_seconds')->default(0);
            $table->foreignId('blocker_category_id')->nullable()->constrained('blocker_categories')->nullOnDelete();
            $table->text('blocker_reason')->nullable();
            $table->date('blocker_expected_resolution')->nullable();
            $table->foreignId('blocked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('blocked_at')->nullable();
            $table->text('cancelled_reason')->nullable();
            $table->timestamps();

            $table->index(['department_id', 'status']);
            $table->index(['project_id', 'status']);
            $table->index(['claimed_by_user_id', 'status']);
            $table->index(['status', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
