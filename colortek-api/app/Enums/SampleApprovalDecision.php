<?php

declare(strict_types=1);

namespace App\Enums;

enum SampleApprovalDecision: string
{
    case Approved = 'approved';
    case Rejected = 'rejected';
}
