<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('samples', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 60)->unique();
            $table->foreignId('client_id')->constrained();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('parent_sample_id')->nullable()->constrained('samples')->nullOnDelete();
            $table->unsignedBigInteger('root_sample_id')->nullable();
            $table->unsignedSmallInteger('attempt_number')->default(1);
            $table->foreignId('requested_by_user_id')->constrained('users');
            $table->timestamp('requested_at');
            $table->date('needed_by')->nullable();
            $table->string('color', 120);
            $table->string('texture', 120)->nullable();
            $table->string('client_reference', 200)->nullable();
            $table->string('size', 60)->nullable();
            $table->text('finish_requirement')->nullable();
            $table->text('notes')->nullable();
            $table->text('modification_reason')->nullable();
            $table->string('status', 40);
            $table->unsignedBigInteger('approved_formula_id')->nullable();
            $table->boolean('is_presale')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->index('root_sample_id');
        });
        Schema::table('samples', function (Blueprint $table): void {
            $table->foreign('root_sample_id')->references('id')->on('samples');
        });
        Schema::create('formulas', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 60)->unique();
            $table->foreignId('sample_id')->constrained();
            $table->unsignedSmallInteger('version')->default(1);
            $table->text('body')->nullable();
            $table->foreignId('author_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('author_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('authored_at')->nullable();
            $table->foreignId('registered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('registered_at')->nullable();
            $table->string('status', 30);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['sample_id', 'version']);
        });
        Schema::table('samples', function (Blueprint $table): void {
            $table->foreign('approved_formula_id')->references('id')->on('formulas')->nullOnDelete();
        });
        Schema::create('sample_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sample_id')->constrained();
            $table->string('type', 20);
            $table->string('decision', 20)->nullable();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('client_signatory_name', 150)->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('recorded_by_user_id')->constrained('users');
            $table->text('comments')->nullable();
            $table->timestamp('form_generated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sample_approvals');
        Schema::table('samples', fn (Blueprint $table) => $table->dropForeign(['approved_formula_id']));
        Schema::dropIfExists('formulas');
        Schema::dropIfExists('samples');
    }
};
