<?php

declare(strict_types=1);

namespace App\Services\Samples;

use App\Models\Sample;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;

final class ApprovalFormGenerator
{
    public function generate(Sample $sample): string
    {
        $sample->loadMissing(['client', 'project.quotation', 'parentSample']);

        $pdf = Pdf::loadView('pdf.sample-approval-form', [
            'sample' => $sample,
            'generatedAt' => CarbonImmutable::now(),
        ]);

        return $pdf->output();
    }
}
