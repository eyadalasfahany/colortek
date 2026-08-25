<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class PaymentQueried implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public Payment $payment, public User $user, public string $note) {}
}
