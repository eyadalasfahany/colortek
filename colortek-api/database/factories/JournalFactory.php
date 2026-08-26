<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\JournalStatus;
use App\Models\Journal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Journal>
 */
class JournalFactory extends Factory
{
    protected $model = Journal::class;

    public function definition(): array
    {
        return [
            'journal_date' => now()->toDateString(),
            'status' => JournalStatus::Open,
            'total_amount' => 0,
        ];
    }
}
