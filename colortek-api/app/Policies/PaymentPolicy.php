<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Payment;
use App\Models\Project;
use App\Models\User;

final class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('payment.view');
    }

    public function view(User $user, Payment $payment): bool
    {
        return $user->can('payment.view');
    }

    public function start(User $user, Project $project): bool
    {
        return $user->can('payment.confirm');
    }
}
