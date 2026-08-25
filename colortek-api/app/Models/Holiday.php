<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\HolidayType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

/**
 * @property CarbonImmutable $date
 * @property bool $is_recurring
 */
final class Holiday extends Model
{
    use HasTranslations;

    /** @var array<int, string> */
    public array $translatable = ['name'];

    protected $fillable = [
        'date',
        'name',
        'type',
        'is_recurring',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'immutable_date',
            'type' => HolidayType::class,
            'is_recurring' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
