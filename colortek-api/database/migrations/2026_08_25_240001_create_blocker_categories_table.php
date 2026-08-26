<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blocker_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->json('name');
            $table->boolean('requires_expected_date')->default(false);
            $table->foreignId('notifies_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocker_categories');
    }
};
