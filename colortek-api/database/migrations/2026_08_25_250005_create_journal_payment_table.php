<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_payment', function (Blueprint $table): void {
            $table->foreignId('journal_id')->constrained('journals')->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->decimal('amount_snapshot', 14, 2);
            $table->primary(['journal_id', 'payment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_payment');
    }
};
