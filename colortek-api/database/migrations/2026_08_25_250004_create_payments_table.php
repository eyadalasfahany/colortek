<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('quotation_id')->nullable()->constrained('quotations')->nullOnDelete();
            $table->unsignedSmallInteger('installment_number');
            $table->decimal('amount', 14, 2)->default(0);
            $table->char('currency', 3)->default('EGP');
            $table->enum('method', ['bank_transfer', 'cash', 'cheque', 'other'])->default('bank_transfer');
            $table->date('paid_at');
            $table->foreignId('confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('journal_id')->nullable()->constrained('journals')->nullOnDelete();
            $table->enum('status', ['pending_confirmation', 'confirmed', 'reviewed', 'journaled', 'accounted'])->default('pending_confirmation');
            $table->text('notes')->nullable();
            $table->string('odoo_payment_ref', 60)->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'installment_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
