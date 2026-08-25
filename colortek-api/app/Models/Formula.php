<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FormulaStatus;
use Carbon\CarbonImmutable;
use Database\Factories\FormulaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property FormulaStatus $status
 * @property int $id
 * @property string $reference
 * @property int $sample_id
 * @property int $version
 * @property string|null $body
 * @property int|null $author_employee_id
 * @property Carbon|null $authored_at
 * @property CarbonImmutable|null $registered_at
 * @property-read Sample $sample
 * @property-read Employee|null $authorEmployee
 * @property-read User|null $registeredBy
 */
final class Formula extends Model
{
    /** @use HasFactory<FormulaFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'reference', 'sample_id', 'version', 'body', 'author_employee_id', 'author_user_id', 'authored_at',
        'registered_by_user_id', 'registered_at', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'authored_at' => 'date',
            'registered_at' => 'immutable_datetime',
            'status' => FormulaStatus::class,
        ];
    }

    /** @return BelongsTo<Sample, $this> */
    public function sample(): BelongsTo
    {
        return $this->belongsTo(Sample::class);
    }

    /** @return BelongsTo<Employee, $this> */
    public function authorEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'author_employee_id');
    }

    /** @return BelongsTo<User, $this> */
    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by_user_id');
    }

    /** @return MorphMany<Attachment, $this> */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
