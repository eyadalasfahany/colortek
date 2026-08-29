<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\QuotationStatus;
use App\Models\Quotation;
use App\Models\User;

final class QuotationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('quotation.manage') || $user->can('project.view');
    }

    public function view(User $user, Quotation $quotation): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('quotation.manage');
    }

    public function update(User $user, Quotation $quotation): bool
    {
        // A locked quotation is frozen once payment 1 is confirmed;
        // only a super admin may still correct it. workflow 01.
        if ($quotation->status === QuotationStatus::Locked) {
            return $user->hasRole('super_admin');
        }

        return $user->can('quotation.manage');
    }
}
