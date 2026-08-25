<?php
declare(strict_types=1);
namespace App\Services\Site;
use App\Models\SiteVisit;
use Barryvdh\DomPDF\Facade\Pdf;
final class SiteVisitPdfGenerator {
 public function generate(SiteVisit $visit) {
  $visit->loadMissing(['engineer','measurements.deductions','answers.checklistItem']);
  return Pdf::loadView('pdf.site-visit', ['visit'=>$visit])->setPaper('a4');
 }
}
