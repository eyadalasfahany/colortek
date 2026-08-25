<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Department;
use App\Models\WorkflowTaskDefinition;
use App\Models\WorkflowTemplate;
use App\Models\WorkflowTransition;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

final class PaymentWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $departments = Department::query()
            ->whereIn('code', ['sales', 'reception', 'accounting'])
            ->get()
            ->keyBy('code');

        $template = WorkflowTemplate::updateOrCreate(
            ['code' => 'payment_cycle', 'version' => 1],
            [
                'name_en' => 'Payment cycle',
                'name_ar' => 'دورة الدفع',
                'scope' => 'payment',
                'is_active' => true,
                'published_at' => now(),
            ],
        );

        $definitions = $this->seedDefinitions($template, $departments);

        $this->seedTransitions($template, $definitions);
    }

    /**
     * @param  Collection<string, Department>  $departments
     * @return array<string, WorkflowTaskDefinition>
     */
    private function seedDefinitions(WorkflowTemplate $template, $departments): array
    {
        $configs = [
            'sales_confirm_payment' => [
                'title_en' => 'Confirm payment',
                'title_ar' => 'تأكيد الدفع',
                'instructions_en' => 'Record the client payment with proof and lock the quotation in Odoo first.',
                'instructions_ar' => 'سجل دفعة العميل مع إثبات الدفع بعد قفل عرض السعر في Odoo.',
                'department' => 'sales',
                'is_entry_point' => true,
                'sla_minutes' => 240,
                'escalate_after_minutes' => 480,
                'required_fields' => ['installment_number', 'amount', 'method', 'paid_at', 'quotation_locked'],
                'required_attachment_types' => ['payment_proof'],
                'form_schema' => [
                    'fields' => [
                        ['key' => 'installment_number', 'type' => 'number', 'label_en' => 'Installment', 'label_ar' => 'القسط', 'required' => true],
                        ['key' => 'amount', 'type' => 'money', 'label_en' => 'Amount paid', 'label_ar' => 'المبلغ المدفوع', 'required' => true],
                        ['key' => 'method', 'type' => 'select', 'options' => ['bank_transfer', 'cash', 'cheque', 'other'], 'label_en' => 'Method', 'label_ar' => 'طريقة الدفع', 'required' => true],
                        ['key' => 'paid_at', 'type' => 'date', 'label_en' => 'Paid on', 'label_ar' => 'تاريخ الدفع', 'required' => true],
                        ['key' => 'quotation_locked', 'type' => 'boolean', 'label_en' => 'Quotation locked in Odoo', 'label_ar' => 'عرض السعر مقفول', 'required' => true],
                        ['key' => 'notes', 'type' => 'textarea', 'label_en' => 'Notes', 'label_ar' => 'ملاحظات', 'required' => false],
                    ],
                ],
            ],
            'reception_review_payment' => [
                'title_en' => 'Review payment',
                'title_ar' => 'مراجعة الدفع',
                'instructions_en' => 'Review what Sales submitted. Accept or query back to Sales.',
                'instructions_ar' => 'راجع ما أرسله المبيعات. قبول أو استفسار.',
                'department' => 'reception',
                'is_entry_point' => false,
                'sla_minutes' => 240,
                'escalate_after_minutes' => 480,
                'required_fields' => ['review_result'],
                'required_attachment_types' => [],
                'form_schema' => [
                    'fields' => [
                        ['key' => 'review_result', 'type' => 'select', 'options' => ['accepted', 'query'], 'label_en' => 'Result', 'label_ar' => 'النتيجة', 'required' => true],
                        ['key' => 'review_note', 'type' => 'textarea', 'label_en' => 'Note', 'label_ar' => 'ملاحظة', 'required' => false],
                    ],
                ],
            ],
            'sales_clarify_payment' => [
                'title_en' => 'Clarify payment',
                'title_ar' => 'توضيح الدفع',
                'instructions_en' => 'Reception queried this payment. Fix and resubmit.',
                'instructions_ar' => 'الاستقبال استفسر عن هذه الدفعة. صحح وأعد الإرسال.',
                'department' => 'sales',
                'is_entry_point' => false,
                'sla_minutes' => 240,
                'escalate_after_minutes' => 480,
                'required_fields' => ['clarification'],
                'required_attachment_types' => [],
                'form_schema' => [
                    'fields' => [
                        ['key' => 'clarification', 'type' => 'textarea', 'label_en' => 'Clarification', 'label_ar' => 'التوضيح', 'required' => true],
                    ],
                ],
            ],
            'reception_daily_journal' => [
                'title_en' => 'Daily journal',
                'title_ar' => 'اليومية',
                'instructions_en' => 'Submit today\'s payment journal.',
                'instructions_ar' => 'قدم يومية مدفوعات اليوم.',
                'department' => 'reception',
                'is_entry_point' => false,
                'sla_minutes' => null,
                'escalate_after_minutes' => null,
                'required_fields' => [],
                'required_attachment_types' => [],
                'form_schema' => [
                    'fields' => [
                        ['key' => 'odoo_journal_ref', 'type' => 'text', 'label_en' => 'Odoo journal ref', 'label_ar' => 'مرجع Odoo', 'required' => false],
                        ['key' => 'notes', 'type' => 'textarea', 'label_en' => 'Notes', 'label_ar' => 'ملاحظات', 'required' => false],
                    ],
                ],
            ],
            'accounting_process_journal' => [
                'title_en' => 'Process journal',
                'title_ar' => 'معالجة اليومية',
                'instructions_en' => 'Process the submitted journal in accounting.',
                'instructions_ar' => 'عالج اليومية المقدمة في المحاسبة.',
                'department' => 'accounting',
                'is_entry_point' => false,
                'sla_minutes' => 480,
                'escalate_after_minutes' => 960,
                'required_fields' => ['accounting_result'],
                'required_attachment_types' => [],
                'form_schema' => [
                    'fields' => [
                        ['key' => 'accounting_result', 'type' => 'select', 'options' => ['processed', 'query'], 'label_en' => 'Result', 'label_ar' => 'النتيجة', 'required' => true],
                        ['key' => 'odoo_reference', 'type' => 'text', 'label_en' => 'Odoo reference', 'label_ar' => 'مرجع Odoo', 'required' => false],
                        ['key' => 'note', 'type' => 'textarea', 'label_en' => 'Note', 'label_ar' => 'ملاحظة', 'required' => false],
                    ],
                ],
            ],
            'reception_fix_journal' => [
                'title_en' => 'Fix journal',
                'title_ar' => 'تصحيح اليومية',
                'instructions_en' => 'Accounting queried the journal. Fix and resubmit.',
                'instructions_ar' => 'المحاسبة استفرت عن اليومية. صحح وأعد الإرسال.',
                'department' => 'reception',
                'is_entry_point' => false,
                'sla_minutes' => 240,
                'escalate_after_minutes' => 480,
                'required_fields' => ['fix_note'],
                'required_attachment_types' => [],
                'form_schema' => [
                    'fields' => [
                        ['key' => 'fix_note', 'type' => 'textarea', 'label_en' => 'Fix note', 'label_ar' => 'ملاحظة التصحيح', 'required' => true],
                    ],
                ],
            ],
        ];

        $definitions = [];

        foreach ($configs as $code => $config) {
            $definitions[$code] = WorkflowTaskDefinition::updateOrCreate(
                ['template_id' => $template->id, 'code' => $code],
                [
                    'title_en' => $config['title_en'],
                    'title_ar' => $config['title_ar'],
                    'instructions_en' => $config['instructions_en'],
                    'instructions_ar' => $config['instructions_ar'],
                    'department_id' => $departments[$config['department']]->id,
                    'is_entry_point' => $config['is_entry_point'],
                    'sla_minutes' => $config['sla_minutes'],
                    'escalate_after_minutes' => $config['escalate_after_minutes'],
                    'required_fields' => $config['required_fields'],
                    'required_attachment_types' => $config['required_attachment_types'],
                    'form_schema' => $config['form_schema'],
                    'requires_timer' => false,
                    'blocks_when_site_not_ready' => false,
                ],
            );
        }

        return $definitions;
    }

    /** @param array<string, WorkflowTaskDefinition> $definitions */
    private function seedTransitions(WorkflowTemplate $template, array $definitions): void
    {
        WorkflowTransition::query()->where('template_id', $template->id)->delete();

        $transitions = [
            [
                'from' => null,
                'to' => 'sales_confirm_payment',
                'condition' => null,
                'join_mode' => 'all',
                'sort_order' => 0,
            ],
            [
                'from' => 'sales_confirm_payment',
                'to' => 'reception_review_payment',
                'condition' => null,
                'join_mode' => 'any',
                'sort_order' => 1,
            ],
            [
                'from' => 'reception_review_payment',
                'to' => 'reception_daily_journal',
                'condition' => ['field' => 'review_result', 'operator' => 'equals', 'value' => 'accepted'],
                'join_mode' => 'any',
                'sort_order' => 1,
            ],
            [
                'from' => 'reception_review_payment',
                'to' => 'sales_clarify_payment',
                'condition' => ['field' => 'review_result', 'operator' => 'equals', 'value' => 'query'],
                'join_mode' => 'any',
                'sort_order' => 2,
            ],
            [
                'from' => 'sales_clarify_payment',
                'to' => 'reception_review_payment',
                'condition' => null,
                'join_mode' => 'any',
                'sort_order' => 1,
            ],
            [
                'from' => 'reception_daily_journal',
                'to' => 'accounting_process_journal',
                'condition' => null,
                'join_mode' => 'all',
                'sort_order' => 1,
            ],
            [
                'from' => 'accounting_process_journal',
                'to' => 'reception_fix_journal',
                'condition' => ['field' => 'accounting_result', 'operator' => 'equals', 'value' => 'query'],
                'join_mode' => 'any',
                'sort_order' => 1,
            ],
            [
                'from' => 'reception_fix_journal',
                'to' => 'accounting_process_journal',
                'condition' => null,
                'join_mode' => 'any',
                'sort_order' => 1,
            ],
        ];

        foreach ($transitions as $transition) {
            WorkflowTransition::create([
                'template_id' => $template->id,
                'from_task_definition_id' => $transition['from'] === null
                    ? null
                    : $definitions[$transition['from']]->id,
                'to_task_definition_id' => $definitions[$transition['to']]->id,
                'condition' => $transition['condition'],
                'join_mode' => $transition['join_mode'],
                'sort_order' => $transition['sort_order'],
            ]);
        }
    }
}
