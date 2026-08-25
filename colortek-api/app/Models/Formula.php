<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FormulaStatus;
use Database\Factories\FormulaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

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

    public function sample(): BelongsTo { return $this->belongsTo(Sample::class); }
    public function authorEmployee(): BelongsTo { return $this->belongsTo(Employee::class, 'author_employee_id'); }
    public function registeredBy(): BelongsTo { return $this->belongsTo(User::class, 'registered_by_user_id'); }
    public function attachments(): MorphMany { return $this->morphMany(Attachment::class, 'attachable'); }
}
