<?php

declare(strict_types=1);

namespace App\Services\Samples;

use App\Enums\FormulaStatus;
use App\Enums\SampleStatus;
use App\Exceptions\TaskNotReadyToComplete;
use App\Models\Attachment;
use App\Models\Formula;
use App\Models\Sample;
use App\Models\User;
use App\Repositories\FormulaRepository;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class FormulaService
{
    public function __construct(
        private FormulaRepository $repository,
        private SampleReferenceGenerator $referenceGenerator,
        private AuditLogger $auditLogger,
    ) {}

    /** @param list<string> $relations */
    public function findOrFail(int $id, array $relations = []): Formula
    {
        return $this->repository->findOneOrFail($id, $relations);
    }

    /** @return list<Formula> */
    public function forSample(Sample $sample): array
    {
        return Formula::query()
            ->where('sample_id', $sample->id)
            ->with(['authorEmployee', 'registeredBy', 'attachments'])
            ->orderBy('version')
            ->get()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $attachmentIds
     */
    public function author(Sample $sample, array $data, array $attachmentIds, User $user): Formula
    {
        $body = isset($data['body']) ? trim((string) $data['body']) : '';
        $sheetIds = $this->resolveAttachmentIdsForType($attachmentIds, 'formula_sheet');

        if ($body === '' && $sheetIds === []) {
            throw new TaskNotReadyToComplete(
                __('A formula requires text or a scanned sheet.'),
                'formula.missing_content',
            );
        }

        return DB::transaction(function () use ($sample, $data, $user, $body, $sheetIds): Formula {
            $version = Formula::query()->where('sample_id', $sample->id)->max('version');
            $version = $version === null ? 1 : ((int) $version + 1);

            $formula = $this->repository->create([
                'reference' => $this->referenceGenerator->forFormula($sample),
                'sample_id' => $sample->id,
                'version' => $version,
                'body' => $body !== '' ? $body : null,
                'author_employee_id' => $data['author_employee_id'] ?? null,
                'author_user_id' => $user->id,
                'authored_at' => isset($data['authored_at'])
                    ? CarbonImmutable::parse((string) $data['authored_at'])->toDateString()
                    : now()->toDateString(),
                'status' => FormulaStatus::Draft,
            ]);

            $this->linkAttachments($sheetIds, $formula);

            return $formula->fresh(['authorEmployee', 'attachments']);
        });
    }

    /** @param array<string, mixed> $data */
    public function register(Formula $formula, array $data, User $user): Formula
    {
        if ($formula->status !== FormulaStatus::Draft) {
            throw new TaskNotReadyToComplete(
                __('Only draft formulas can be registered.'),
                'formula.not_draft',
            );
        }

        $body = $formula->body ?? '';
        if (isset($data['corrections']) && trim((string) $data['corrections']) !== '') {
            $body = trim($body."\n\n[Correction] ".trim((string) $data['corrections']));
        }

        return DB::transaction(function () use ($formula, $data, $user, $body): Formula {
            $oldBody = $formula->body;
            $formula->update([
                'body' => $body !== '' ? $body : $formula->body,
                'status' => FormulaStatus::Registered,
                'registered_by_user_id' => $user->id,
                'registered_at' => now(),
                'notes' => $data['notes'] ?? $formula->notes,
            ]);

            if (isset($data['corrections']) && trim((string) $data['corrections']) !== '') {
                $this->auditLogger->log(
                    auditable: $formula,
                    event: 'corrected',
                    user: $user,
                    oldValues: ['body' => $oldBody],
                    newValues: ['body' => $formula->body],
                    reason: (string) $data['corrections'],
                );
            }

            $sample = $formula->sample()->first();
            if ($sample !== null) {
                $sample->update(['status' => SampleStatus::ReadyForClientApproval]);
            }

            return $formula->fresh(['authorEmployee', 'registeredBy', 'attachments', 'sample']);
        });
    }

    /** @param array<string, mixed> $data */
    public function updateRegistered(Formula $formula, array $data, User $user): Formula
    {
        if (! in_array($formula->status, [FormulaStatus::Registered, FormulaStatus::Approved], true)) {
            throw new TaskNotReadyToComplete(
                __('Only registered formulas can be corrected.'),
                'formula.not_registered',
            );
        }

        $correction = trim((string) ($data['corrections'] ?? $data['correction'] ?? ''));
        if ($correction === '') {
            throw TaskNotReadyToComplete::missingField('corrections');
        }

        return DB::transaction(function () use ($formula, $user, $correction): Formula {
            $oldBody = $formula->body;
            $newBody = trim(($formula->body ?? '')."\n\n[Correction] ".$correction);

            $formula->update(['body' => $newBody]);

            $this->auditLogger->log(
                auditable: $formula,
                event: 'corrected',
                user: $user,
                oldValues: ['body' => $oldBody],
                newValues: ['body' => $newBody],
                reason: $correction,
            );

            return $formula->fresh(['authorEmployee', 'registeredBy', 'attachments']);
        });
    }

    public function approveForSample(Sample $sample): ?Formula
    {
        $formula = Formula::query()
            ->where('sample_id', $sample->id)
            ->where('status', FormulaStatus::Registered)
            ->orderByDesc('version')
            ->first();

        if ($formula === null) {
            return null;
        }

        Formula::query()
            ->where('sample_id', $sample->id)
            ->where('id', '!=', $formula->id)
            ->where('status', FormulaStatus::Approved)
            ->update(['status' => FormulaStatus::Superseded]);

        $formula->update(['status' => FormulaStatus::Approved]);
        $sample->update(['approved_formula_id' => $formula->id]);

        return $formula->fresh();
    }

    public function supersedeForSample(Sample $sample): void
    {
        Formula::query()
            ->where('sample_id', $sample->id)
            ->whereIn('status', [FormulaStatus::Draft, FormulaStatus::Registered, FormulaStatus::Approved])
            ->update(['status' => FormulaStatus::Superseded]);
    }

    /** @param list<int> $attachmentIds */
    private function linkAttachments(array $attachmentIds, Formula $formula): void
    {
        if ($attachmentIds === []) {
            return;
        }

        Attachment::query()
            ->whereIn('id', $attachmentIds)
            ->update([
                'attachable_type' => $formula->getMorphClass(),
                'attachable_id' => $formula->id,
            ]);
    }

    /**
     * @param  array<int|string, mixed>  $attachmentIds
     * @return list<int>
     */
    private function resolveAttachmentIdsForType(array $attachmentIds, string $type): array
    {
        if (isset($attachmentIds[$type]) && is_array($attachmentIds[$type])) {
            return array_map(intval(...), $attachmentIds[$type]);
        }

        if ($this->isFlatAttachmentList($attachmentIds)) {
            return array_map(intval(...), $attachmentIds);
        }

        return [];
    }

    /** @param array<int|string, mixed> $attachmentIds */
    private function isFlatAttachmentList(array $attachmentIds): bool
    {
        if ($attachmentIds === []) {
            return true;
        }

        return array_keys($attachmentIds) === range(0, count($attachmentIds) - 1);
    }
}
