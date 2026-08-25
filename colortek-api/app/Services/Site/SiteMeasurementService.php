<?php

declare(strict_types=1);

namespace App\Services\Site;

use App\Enums\DeductionSign;
use App\Models\SiteMeasurement;
use App\Models\SiteVisit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class SiteMeasurementService
{
    /** @param list<array<string, mixed>> $rows */
    public function bulkUpsert(SiteVisit $visit, array $rows, ?string $idempotencyKey = null): array
    {
        if ($idempotencyKey !== null) {
            $cacheKey = sprintf('site_measurements:%d:%s', $visit->id, $idempotencyKey);
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return [
                    'measurements' => SiteMeasurement::query()->whereIn('id', $cached)->with('deductions')->orderBy('sort_order')->get(),
                    'idempotent' => true,
                ];
            }
        }

        $savedIds = DB::transaction(function () use ($visit, $rows): array {
            $visit->measurements()->each(fn (SiteMeasurement $m) => $m->deductions()->delete());
            $visit->measurements()->delete();
            $savedIds = [];
            $currentGroupId = null;
            foreach ($rows as $index => $row) {
                $elementName = ! empty($row['element_name']) ? (string) $row['element_name'] : null;
                $measurement = $visit->measurements()->create([
                    'page_number' => (int) ($row['page_number'] ?? 1),
                    'line_number' => (int) ($row['line_number'] ?? ($index + 1)),
                    'element_name' => $elementName,
                    'element_group_id' => $elementName === null ? $currentGroupId : null,
                    'height_m' => $row['height_m'] ?? null,
                    'length_m' => $row['length_m'] ?? null,
                    'width_m' => $row['width_m'] ?? null,
                    'thickness_m' => $row['thickness_m'] ?? null,
                    'diameter_m' => $row['diameter_m'] ?? null,
                    'other_note' => $row['other_note'] ?? null,
                    'area_sqm' => null,
                    'verified' => (bool) ($row['verified'] ?? false),
                    'sort_order' => (int) ($row['sort_order'] ?? $index),
                ]);
                if ($elementName !== null) {
                    $currentGroupId = $measurement->id;
                    $measurement->update(['element_group_id' => $currentGroupId]);
                }
                foreach ($row['deductions'] ?? [] as $dIndex => $deduction) {
                    $measurement->deductions()->create([
                        'kind' => $deduction['kind'] ?? 'other',
                        'label' => $deduction['label'] ?? null,
                        'count' => (int) ($deduction['count'] ?? 1),
                        'length_m' => $deduction['length_m'] ?? null,
                        'width_m' => $deduction['width_m'] ?? null,
                        'sign' => DeductionSign::from((string) ($deduction['sign'] ?? 'subtract')),
                        'sort_order' => (int) ($deduction['sort_order'] ?? $dIndex),
                    ]);
                }
                $savedIds[] = $measurement->id;
            }

            return $savedIds;
        });

        if ($idempotencyKey !== null) {
            Cache::put(sprintf('site_measurements:%d:%s', $visit->id, $idempotencyKey), $savedIds, now()->addDay());
        }

        return [
            'measurements' => SiteMeasurement::query()->whereIn('id', $savedIds)->with('deductions')->orderBy('sort_order')->get(),
            'idempotent' => false,
        ];
    }
}
