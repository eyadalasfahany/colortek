<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DepartmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

final class Department extends Model
{
    /** @use HasFactory<DepartmentFactory> */
    use HasFactory;

    use HasTranslations;
    use SoftDeletes;

    /** @var array<int, string> */
    public array $translatable = ['name'];

    /** @var list<string> */
    protected $fillable = ['code', 'name', 'is_queue', 'active'];

    protected function casts(): array
    {
        return [
            'is_queue' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('is_supervisor');
    }
}
