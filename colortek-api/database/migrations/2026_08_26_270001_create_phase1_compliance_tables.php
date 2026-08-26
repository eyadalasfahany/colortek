<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('crew_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->date('log_date');
            $table->foreignId('supervisor_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->text('work_done');
            $table->string('weather_note', 120)->nullable();
            $table->text('issues')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'log_date']);
        });

        Schema::create('crew_log_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('crew_log_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->decimal('hours', 5, 2);
            $table->string('role_note', 120)->nullable();
        });

        Schema::create('idempotency_keys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('key', 255);
            $table->string('route_fingerprint', 255);
            $table->unsignedSmallInteger('response_code');
            $table->json('response_body');
            $table->timestamps();

            $table->unique(['user_id', 'key', 'route_fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
        Schema::dropIfExists('crew_log_members');
        Schema::dropIfExists('crew_logs');
        Schema::dropIfExists('task_comments');
    }
};
