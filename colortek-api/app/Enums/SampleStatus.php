<?php

declare(strict_types=1);

namespace App\Enums;

enum SampleStatus: string
{
    case Draft = 'draft';
    case PendingManagerApproval = 'pending_manager_approval';
    case RejectedByManager = 'rejected_by_manager';
    case InWorkshop = 'in_workshop';
    case AwaitingFormulaRegistration = 'awaiting_formula_registration';
    case ReadyForClientApproval = 'ready_for_client_approval';
    case Approved = 'approved';
    case RejectedByClient = 'rejected_by_client';
    case Superseded = 'superseded';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::PendingManagerApproval => __('Pending manager approval'),
            self::RejectedByManager => __('Rejected by manager'),
            self::InWorkshop => __('In workshop'),
            self::AwaitingFormulaRegistration => __('Awaiting formula registration'),
            self::ReadyForClientApproval => __('Ready for client approval'),
            self::Approved => __('Approved'),
            self::RejectedByClient => __('Rejected by client'),
            self::Superseded => __('Superseded'),
            self::Cancelled => __('Cancelled'),
        };
    }
}