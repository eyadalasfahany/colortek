<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\HolidayType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

final class Holiday extends Model
{
    use HasTranslations;

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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
