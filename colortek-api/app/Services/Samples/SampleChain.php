<?php

declare(strict_types=1);

namespace App\Services\Samples;

use App\Models\Sample;

final class SampleChain
{
    /** @return array{count: int, attempts: list<array<string, mixed>>} */
    public function build(Sample $sample): array
    {
        $rootId = $sample->root_sample_id ?? $sample->id;

        $attempts = Sample::query()
            ->where('root_sample_id', $rootId)
            ->with(['approvals', 'formulas'])
            ->orderByDesc('attempt_number')
            ->get();

        $entries = $attempts->map(function (Sample $attempt): array {
            $clientRejection = $attempt->approvals
                ->first(fn ($approval) => $approval->type->value === 'client'
                    && $approval->decision?->value === 'rejected');

            $formula = $attempt->formulas->sortByDesc('version')->first();

            return [
                'id' => $attempt->id,
                'reference' => $attempt->reference,
                'attempt_number' => $attempt->attempt_number,
                'status' => $attempt->status->value,
                'rejection_reason' => $clientRejection?->comments,
                'formula_reference' => $formula?->reference,
                'is_current' => $attempt->id === $attempt->id,
            ];
        })->all();

        return [
            'count' => count($entries),
            'attempts' => $entries,
        ];
    }

    public function countForRoot(int $rootSampleId): int
    {
        return Sample::query()->where('root_sample_id', $rootSampleId)->count();
    }
}
