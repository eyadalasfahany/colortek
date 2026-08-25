<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkflowTemplate;
use App\Repositories\PaymentRepository;
use App\Services\Workflow\WorkflowEngine;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class PaymentService
{
    public function __construct(
        private PaymentRepository $repository,
        private WorkflowEngine $workflowEngine,
    ) {}

    /** @return LengthAwarePaginator<int, Payment> */
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->paginate(
            $this->repository->baseQuery()->with(['project', 'quotation'])->latest('id'),
            $perPage,
        );
    }

    /** @param list<string> $relations */
    public function findOrFail(int $id, array $relations = []): Payment
    {
        /** @var Payment $payment */
        $payment = $this->repository->findOneOrFail($id, $relations);

        return $payment;
    }

    /** @return array{payment: Payment, task: Task} */
    public function startForProject(Project $project, int $installmentNumber, User $user): array
    {
        $template = WorkflowTemplate::query()
            ->where('code', 'payment_cycle')
            ->where('is_active', true)
            ->whereNotNull('published_at')
            ->first();

        if ($template === null) {
            throw new ModelNotFoundException(__('Payment workflow template not found'));
        }

        return DB::transaction(function () use ($project, $installmentNumber, $template): array {
            $payment = $this->repository->create([
                'project_id' => $project->id,
                'quotation_id' => $project->quotation_id,
                'installment_number' => $installmentNumber,
                'amount' => 0,
                'currency' => 'EGP',
                'method' => 'bank_transfer',
                'paid_at' => CarbonImmutable::today()->toDateString(),
                'status' => PaymentStatus::PendingConfirmation,
            ]);

            $instance = $this->workflowEngine->start($template, $payment);
            $task = $instance->tasks()->whereHas('definition', fn ($q) => $q->where('code', 'sales_confirm_payment'))->firstOrFail();

            /** @var Payment $freshPayment */
            $freshPayment = $payment->fresh(['project', 'quotation']);

            return [
                'payment' => $freshPayment,
                'task' => $task->load(['department', 'definition']),
            ];
        });
    }
}
