<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CrewLogMember extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'crew_log_id',
        'employee_id',
        'hours',
        'role_note',
    ];

    protected function casts(): array
    {
        return [
            'hours' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<CrewLog, $this> */
    public function crewLog(): BelongsTo
    {
        return $this->belongsTo(CrewLog::class);
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
