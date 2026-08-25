<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeductionSign;
use Database\Factories\SiteMeasurementDeductionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class SiteMeasurementDeduction extends Model
{
    /** @use HasFactory<SiteMeasurementDeductionFactory> */
    use HasFactory;

    protected $fillable = ['measurement_id', 'kind', 'label', 'count', 'length_m', 'width_m', 'sign', 'sort_order'];

    protected function casts(): array
    {
        return ['count' => 'integer', 'sign' => DeductionSign::class, 'sort_order' => 'integer'];
    }
}
