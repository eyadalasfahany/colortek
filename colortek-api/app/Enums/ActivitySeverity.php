<?php

declare(strict_types=1);

namespace App\Enums;

enum ActivitySeverity: string
{
    case Info = 'info';
    case Success = 'success';
    case Warning = 'warning';
    case Blocker = 'blocker';
    case Approval = 'approval';
}
