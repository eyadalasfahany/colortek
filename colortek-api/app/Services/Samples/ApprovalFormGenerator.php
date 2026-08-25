<?php

declare(strict_types=1);

namespace App\Services\Samples;

use App\Models\Sample;
use App\Models\SampleApproval;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;

final class ApprovalFormGenerator
{
    public function generate(Sample $sample): string
    {
        $sample->loadMissing(['client', 'project.quotation', 'parentSample.approvals']);

        $previousRejection = null;
        if ($sample->attempt_number > 1 && $sample->parentSample !== null) {
            $previousRejection = $sample->parentSample->approvals
                ->first(fn (SampleApproval $approval) => $approval->type->value === 'client'
                    && $approval->decision?->value === 'rejected')?->comments;
        }

        $pdf = Pdf::loadView('pdf.sample-approval-form', [
            'sample' => $sample,
            'generatedAt' => CarbonImmutable::now(),
            'previousRejection' => $previousRejection,
        ]);

        return $pdf->output();
    }
}
