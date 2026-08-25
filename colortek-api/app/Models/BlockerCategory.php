<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\BlockerCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

final class BlockerCategory extends Model
{
    /** @use HasFactory<BlockerCategoryFactory> */
    use HasFactory;

    use HasTranslations;
    use SoftDeletes;

    public array $translatable = ['name'];

    protected $fillable = [
        'code',
        'name',
        'requires_expected_date',
        'notifies_department_id',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'requires_expected_date' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function notifiesDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'notifies_department_id');
    }
}
