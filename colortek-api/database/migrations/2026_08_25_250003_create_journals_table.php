<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journals', function (Blueprint $table): void {
            $table->id();
            $table->date('journal_date')->unique();
            $table->enum('status', ['open', 'submitted', 'accounted'])->default('open');
            $table->foreignId('prepared_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('accounted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accounted_at')->nullable();
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->string('odoo_journal_ref', 60)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journals');
    }
};
