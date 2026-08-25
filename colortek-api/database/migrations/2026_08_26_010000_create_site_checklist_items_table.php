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
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_checklist_items');
    }
};
