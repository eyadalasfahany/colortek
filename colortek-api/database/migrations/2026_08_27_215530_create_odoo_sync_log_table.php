<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records every push the Odoo gateway made — or, in Phase 1, would have made.
 *
 * `specs/13-odoo-gateway-and-seed-data.md` §1: the fake driver writes the full
 * payload here with status `simulated`, so when the real integration is switched
 * on there is a history of exactly what the system intended to send.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('odoo_sync_log', function (Blueprint $table): void {
            $table->id();
            $table->string('operation', 60);
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            // Phase 2 rule: every push carries an idempotency key so a retry
            // cannot double-post. Enforced now so the contract does not change.
            $table->string('idempotency_key', 120)->unique();
            $table->string('driver', 30);
            $table->string('status', 30);
            $table->json('payload');
            $table->json('response')->nullable();
            $table->string('odoo_reference', 120)->nullable();
            $table->text('error')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['operation', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('odoo_sync_log');
    }
};
