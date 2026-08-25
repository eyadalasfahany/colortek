<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('phone', 30)->nullable()->after('password');
            $table->enum('locale', ['en', 'ar'])->default('en')->after('phone');
            $table->boolean('active')->default(true)->after('locale');
            $table->timestamp('last_seen_at')->nullable()->after('active');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('primary_department_id')->nullable()->after('last_seen_at')
                ->constrained('departments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('primary_department_id');
            $table->dropColumn(['phone', 'locale', 'active', 'last_seen_at']);
        });
    }
};
