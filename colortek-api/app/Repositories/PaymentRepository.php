<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/** @extends BaseRepository<Payment> */
final class PaymentRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(Payment::class);
    }

    /** @return Builder<Payment> */
    public function baseQuery(): Builder
    {
        return Payment::query();
    }

    /**
     * @param  Builder<Payment>  $query
     * @return LengthAwarePaginator<int, Payment>
     */
    public function paginate(Builder $query, int $perPage = 15): LengthAwarePaginator
    {
        return parent::paginate($query, $perPage);
    }

    protected function notFoundMessage(): string
    {
        return __('Payment not found');
    }
}
