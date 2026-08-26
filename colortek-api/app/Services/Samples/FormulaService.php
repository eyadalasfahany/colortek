<?php

declare(strict_types=1);

namespace App\Services\Samples;

use App\Enums\FormulaStatus;
use App\Models\Attachment;
use App\Models\Formula;
use App\Models\Sample;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class FormulaService
{
    /** @param list<string> $relations */
    public function findOrFail(int $id, array $relations = []): Formula
    {
        $formula = Formula::query()->with($relations)->find($id);
        if ($formula === null) {
            throw new ModelNotFoundException(__('Formula not found'));
        }

        return $formula;
    }

    /** @return list<Formula> */
    public function forSample(Sample $sample): array
    {
        return Formula::query()
            ->where('sample_id', $sample->id)
            ->orderByDesc('version')
            ->with(['authorEmployee', 'registeredBy', 'attachments'])
            ->get()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>  $attachmentIds
     */
    public function author(Sample $sample, array $data, array $attachmentIds, User $user): Formula
    {
        $version = (int) Formula::query()->where('sample_id', $sample->id)->max('version') + 1;
        $formula = Formula::query()->create([
            'reference' => app(SampleReferenceGenerator::class)->forFormula($sample, $version),
            'sample_id' => $sample->id,
            'version' => $version,
            'body' => $data['body'] ?? null,
            'author_employee_id' => $data['author_employee_id'] ?? null,
            'author_user_id' => $user->id,
            'authored_at' => $data['authored_at'] ?? now(),
            'status' => FormulaStatus::Draft,
        ]);

        if ($attachmentIds !== []) {
            Attachment::query()->whereIn('id', $attachmentIds)->update([
                'attachable_type' => $formula->getMorphClass(),
                'attachable_id' => $formula->id,
            ]);
        }

        return $formula->fresh(['authorEmployee', 'attachments']);
    }

    /** @param array<string, mixed> $data */
    public function register(Formula $formula, array $data, User $user): Formula
    {
        $formula->update([
            'status' => FormulaStatus::Registered,
            'registered_by_user_id' => $user->id,
            'registered_at' => now(),
            'notes' => $data['notes'] ?? $formula->notes,
        ]);

        return $formula->fresh(['authorEmployee', 'registeredBy', 'attachments']);
    }

    /** @param array<string, mixed> $data */
    public function updateRegistered(Formula $formula, array $data, User $user): Formula
    {
        unset($user);
        $formula->update([
            'body' => $data['body'] ?? $formula->body,
            'notes' => $data['notes'] ?? $formula->notes,
        ]);

        return $formula->fresh(['authorEmployee', 'registeredBy', 'attachments']);
    }
}
