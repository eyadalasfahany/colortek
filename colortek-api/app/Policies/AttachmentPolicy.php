<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Attachment;
use App\Models\User;

final class AttachmentPolicy
{
    public function create(User $user): bool
    {
        return $user->can('task.complete') || $user->can('payment.confirm');
    }

    public function view(User $user, Attachment $attachment): bool
    {
        return $user->can('payment.view') || $user->can('task.view_own_queue');
    }

    public function delete(User $user, Attachment $attachment): bool
    {
        return $user->can('task.complete') || $user->can('payment.confirm');
    }
}
