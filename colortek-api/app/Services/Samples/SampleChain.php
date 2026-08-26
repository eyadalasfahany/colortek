<?php

declare(strict_types=1);

namespace App\Services\Samples;

use App\Models\Sample;

final class SampleChain
{
    /** @return array{count: int, attempts: list<array<string, mixed>>} */
    public function build(Sample $sample): array
    {
        $rootId = $sample->root_sample_id ?: $sample->id;
        $attempts = Sample::query()
            ->where('root_sample_id', $rootId)
            ->orderByDesc('attempt_number')
            ->with(['parentSample'])
            ->get();

        return [
            'count' => $attempts->count(),
            'attempts' => $attempts->map(fn (Sample $item): array => [
                'id' => $item->id,
                'reference' => $item->reference,
                'attempt_number' => $item->attempt_number,
                'status' => $item->status->value,
                'is_current' => $item->id === $sample->id,
                'rejection_reason' => $item->modification_reason,
                'parent_reference' => $item->parentSample?->reference,
            ])->values()->all(),
        ];
    }
}
