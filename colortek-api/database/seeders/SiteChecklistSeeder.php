<?php
declare(strict_types=1);
namespace Database\Seeders;
use App\Enums\ChecklistAnswerType;
use App\Models\SiteChecklistItem;
use Illuminate\Database\Seeder;
final class SiteChecklistSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['humidity', 'Humidity level at the site', 'نسبة الرطوبة بالموقع', ChecklistAnswerType::Percentage, '%', false, 1],
            ['site_clear_of_other_workers', 'Site cleared of other contractors workers', 'إخلاء الموقع من عُمال الغير', ChecklistAnswerType::YesNo, null, true, 2],
            ['site_clear_of_obstructions', 'Site cleared of obstructions', 'إخلاء الموقع من أى أثاث أو أدوات تعيق عمل فريق COLORTEK', ChecklistAnswerType::YesNo, null, true, 3],
            ['utilities_available', 'Utilities available', 'توافر المعدات والخدمات اللازمة للعمالة من حيث مياه وكهرباء وسقالات', ChecklistAnswerType::YesNo, null, true, 4],
            ['overall_readiness', 'Overall readiness', 'مدى تجهيز الموقع لبدء تنفيذ أعمال COLORTEK', ChecklistAnswerType::Text, null, false, 5],
        ];
        foreach ($items as [$code, $en, $ar, $type, $unit, $critical, $order]) {
            SiteChecklistItem::updateOrCreate(['code' => $code], [
                'label_en' => $en, 'label_ar' => $ar, 'answer_type' => $type, 'unit' => $unit,
                'is_readiness_critical' => $critical, 'allows_note' => true, 'sort_order' => $order, 'active' => true,
            ]);
        }
    }
}
