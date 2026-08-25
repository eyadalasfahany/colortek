<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TimeEntry extends Model
{
    protected $fillable = [
        'task_id', 'user_id', 'employee_id', 'started_at', 'ended_at', 'seconds', 'source', 'needs_review', 'note',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
            'needs_review' => 'boolean',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
