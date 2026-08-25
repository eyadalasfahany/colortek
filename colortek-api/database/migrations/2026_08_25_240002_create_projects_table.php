<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 60)->unique();
            $table->string('name', 200);
            $table->unsignedBigInteger('client_id')->nullable();
            $table->string('stage', 30)->default('lead');
            $table->string('status', 30)->default('active');
            $table->foreignId('sales_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('block_all_when_site_not_ready')->default(false);
            $table->boolean('site_ready')->default(true);
            $table->json('sla_profile')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
