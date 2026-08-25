<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SiteChecklistItem;
use Illuminate\Database\Seeder;

final class SiteChecklistSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([['humidity', 'Humidity level at the site', 'نسبة الرطوبة بالموقع', 'percentage', '%', false, 1], ['site_clear_of_other_workers', "Site cleared of other contractors' workers", 'إخلاء الموقع من عُمال الغير', 'yes_no', null, true, 2], ['site_clear_of_obstructions', 'Site cleared of furniture or tools blocking the Colortek team', 'إخلاء الموقع من أى أثاث أو أدوات تعيق عمل فريق COLORTEK', 'yes_no', null, true, 3], ['utilities_available', 'Water, electricity and scaffolding available for the workers', 'توافر المعدات والخدمات اللازمة للعمالة من حيث مياه وكهرباء وسقالات', 'yes_no', null, true, 4], ['overall_readiness', 'How ready the site is to begin Colortek works', 'مدى تجهيز الموقع لبدء تنفيذ أعمال COLORTEK', 'text', null, false, 5]] as $i) {
            SiteChecklistItem::updateOrCreate(['code' => $i[0]], ['label_en' => $i[1], 'label_ar' => $i[2], 'answer_type' => $i[3], 'unit' => $i[4], 'is_readiness_critical' => $i[5], 'allows_note' => true, 'sort_order' => $i[6], 'active' => true]);
        }
    }
}
