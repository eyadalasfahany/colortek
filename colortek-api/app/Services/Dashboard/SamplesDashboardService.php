<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Models\User;

final class SamplesDashboardService
{
    public function build(User $u): array
    {
        return ['columns' => ['requested' => [], 'in_workshop' => [], 'awaiting_approval' => [], 'with_client' => [], 'approved' => [], 'rejected' => []], 'stub' => true];
    }
}
