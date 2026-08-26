<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->foreignId('responsible_user_id')->nullable()->after('sales_user_id')->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable()->after('sla_profile');
            $table->foreignId('completed_by_user_id')->nullable()->after('completed_at')->constrained('users')->nullOnDelete();
            $table->text('completion_note')->nullable()->after('completed_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('responsible_user_id');
            $table->dropConstrainedForeignId('completed_by_user_id');
            $table->dropColumn(['completed_at', 'completion_note']);
        });
    }
};
