<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SiteMeasurementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SiteMeasurement extends Model
{
    /** @use HasFactory<SiteMeasurementFactory> */
    use HasFactory;

    protected $fillable = [
        'site_visit_id', 'page_number', 'line_number', 'element_name', 'element_group_id', 'height_m',
        'length_m', 'width_m', 'thickness_m', 'diameter_m', 'other_note', 'area_sqm', 'verified', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'page_number' => 'integer', 'line_number' => 'integer', 'verified' => 'boolean', 'sort_order' => 'integer',
        ];
    }

    /** @return HasMany<SiteMeasurementDeduction, $this> */
    public function deductions(): HasMany
    {
        return $this->hasMany(SiteMeasurementDeduction::class, 'measurement_id')->orderBy('sort_order');
    }
}
