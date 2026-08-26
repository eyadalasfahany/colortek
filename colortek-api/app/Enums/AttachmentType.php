<?php

declare(strict_types=1);

namespace App\Enums;

enum AttachmentType: string
{
    case PaymentProof = 'payment_proof';
    case SamplePhoto = 'sample_photo';
    case FormulaSheet = 'formula_sheet';
    case ClientApprovalForm = 'client_approval_form';
    case SiteVisitSigned = 'site_visit_signed';
    case SitePhoto = 'site_photo';
    case CrewLogPhoto = 'crew_log_photo';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::PaymentProof => __('Payment proof'),
            self::SamplePhoto => __('Sample photo'),
            self::FormulaSheet => __('Formula sheet'),
            self::ClientApprovalForm => __('Client approval form'),
            self::SiteVisitSigned => __('Signed site visit'),
            self::SitePhoto => __('Site photo'),
            self::CrewLogPhoto => __('Crew log photo'),
            self::Other => __('Other'),
        };
    }
}
