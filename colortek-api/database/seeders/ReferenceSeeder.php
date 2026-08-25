<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BlockerCategory;
use App\Models\Department;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class ReferenceSeeder extends Seeder
{
    /** @var list<string> */
    private const PERMISSIONS = [
        'project.view',
        'project.view_all',
        'project.create',
        'project.update',
        'project.change_stage',
        'project.complete',
        'project.cancel',
        'task.view_own_queue',
        'task.view_all',
        'task.claim',
        'task.release',
        'task.complete',
        'task.block',
        'task.unblock',
        'task.comment',
        'task.reassign',
        'task.create_adhoc',
        'task.cancel',
        'task.override_deadline',
        'payment.view',
        'payment.confirm',
        'payment.review',
        'payment.skip_proof',
        'journal.view',
        'journal.prepare',
        'journal.account',
        'journal.reopen',
        'sample.view',
        'sample.create',
        'sample.create_presale',
        'sample.approve_manager',
        'sample.record_client_decision',
        'sample.request_modification',
        'sample.cancel',
        'formula.view',
        'formula.author',
        'formula.register',
        'formula.update_registered',
        'site.view',
        'site.visit_create',
        'site.visit_submit',
        'site.set_readiness',
        'site.override_block',
        'site.corrective_action_manage',
        'site.measurements_edit',
        'time.timer_run',
        'time.timer_run_for_others',
        'time.crew_log_submit',
        'time.correct',
        'time.view_all',
        'user.manage',
        'role.manage',
        'role.assign',
        'holiday.manage',
        'employee.manage',
        'workflow.view',
        'workflow.manage',
        'settings.manage',
        'audit.view',
        'client.manage',
        'quotation.manage',
    ];

    /** @var array<string, array{en: string, ar: string}> */
    private const DEPARTMENTS = [
        'sales' => ['en' => 'Sales', 'ar' => 'المبيعات'],
        'reception' => ['en' => 'Reception', 'ar' => 'الاستقبال'],
        'accounting' => ['en' => 'Accounting', 'ar' => 'المحاسبة'],
        'workshop' => ['en' => 'Workshop', 'ar' => 'الورشة'],
        'tinting' => ['en' => 'Tinting', 'ar' => 'التلوين'],
        'site' => ['en' => 'Site', 'ar' => 'الموقع'],
        'management' => ['en' => 'Management', 'ar' => 'الإدارة'],
        'admin' => ['en' => 'Admin', 'ar' => 'الإدارة التشغيلية'],
    ];

    /** @var array<string, list<string>> */
    private const ROLE_PERMISSIONS = [
        'admin' => [
            'project.view', 'project.view_all', 'project.create', 'project.update', 'project.change_stage',
            'project.complete', 'project.cancel',
            'task.view_own_queue', 'task.view_all', 'task.claim', 'task.release', 'task.complete',
            'task.block', 'task.unblock', 'task.comment', 'task.reassign', 'task.create_adhoc',
            'task.cancel', 'task.override_deadline',
            'payment.view', 'payment.review',
            'journal.view', 'journal.prepare', 'journal.reopen',
            'sample.view', 'sample.create', 'sample.create_presale', 'sample.approve_manager',
            'sample.record_client_decision', 'sample.request_modification', 'sample.cancel',
            'formula.view', 'formula.register', 'formula.update_registered',
            'site.view', 'site.set_readiness', 'site.corrective_action_manage', 'site.measurements_edit',
            'time.view_all',
            'user.manage', 'holiday.manage', 'employee.manage',
            'workflow.view', 'workflow.manage', 'settings.manage', 'audit.view',
            'client.manage', 'quotation.manage',
        ],
        'management' => [
            'project.view', 'project.view_all', 'project.create', 'project.update', 'project.change_stage',
            'project.complete', 'project.cancel',
            'task.view_own_queue', 'task.view_all', 'task.claim', 'task.release', 'task.complete',
            'task.block', 'task.unblock', 'task.comment', 'task.reassign', 'task.create_adhoc',
            'task.cancel', 'task.override_deadline',
            'payment.view', 'journal.view', 'journal.reopen',
            'sample.view', 'sample.create', 'sample.create_presale', 'sample.approve_manager',
            'sample.record_client_decision', 'sample.request_modification', 'sample.cancel',
            'formula.view', 'formula.update_registered',
            'site.view', 'site.set_readiness', 'site.override_block', 'site.corrective_action_manage',
            'site.measurements_edit',
            'time.correct', 'time.view_all',
            'employee.manage', 'workflow.view', 'audit.view',
            'client.manage', 'quotation.manage',
        ],
        'approver' => [
            'project.view', 'task.view_own_queue', 'task.claim', 'task.release', 'task.complete',
            'task.block', 'task.unblock', 'task.comment',
            'sample.view', 'sample.approve_manager',
            'formula.view', 'site.view',
        ],
        'sales' => [
            'project.view', 'project.create', 'project.update',
            'task.view_own_queue', 'task.claim', 'task.release', 'task.complete',
            'task.block', 'task.unblock', 'task.comment',
            'payment.view', 'payment.confirm',
            'sample.view', 'sample.create', 'sample.create_presale',
            'sample.record_client_decision', 'sample.request_modification', 'sample.cancel',
            'formula.view', 'site.view',
            'client.manage', 'quotation.manage',
        ],
        'reception' => [
            'project.view', 'project.view_all', 'project.update', 'project.change_stage',
            'task.view_own_queue', 'task.view_all', 'task.claim', 'task.release', 'task.complete',
            'task.block', 'task.unblock', 'task.comment', 'task.reassign', 'task.create_adhoc',
            'task.override_deadline',
            'payment.view', 'payment.review',
            'journal.view', 'journal.prepare',
            'sample.view', 'sample.create', 'sample.record_client_decision',
            'formula.view', 'formula.register', 'formula.update_registered',
            'site.view', 'site.corrective_action_manage',
            'time.view_all', 'employee.manage', 'workflow.view',
            'client.manage', 'quotation.manage',
        ],
        'accounting' => [
            'project.view', 'project.view_all',
            'task.view_own_queue', 'task.claim', 'task.release', 'task.complete',
            'task.block', 'task.unblock', 'task.comment',
            'payment.view', 'journal.view', 'journal.account',
            'sample.view', 'formula.view', 'site.view', 'time.view_all',
        ],
        'workshop_supervisor' => [
            'project.view', 'task.view_own_queue', 'task.claim', 'task.release', 'task.complete',
            'task.block', 'task.unblock', 'task.comment',
            'sample.view', 'formula.view', 'formula.author', 'site.view',
            'time.timer_run', 'time.timer_run_for_others', 'time.crew_log_submit', 'time.correct',
            'employee.manage',
        ],
        'tinting' => [
            'project.view', 'task.view_own_queue', 'task.claim', 'task.release', 'task.complete',
            'task.block', 'task.unblock', 'task.comment',
            'sample.view', 'formula.view', 'formula.author',
            'time.timer_run', 'time.timer_run_for_others',
        ],
        'site_engineer' => [
            'project.view', 'project.update',
            'task.view_own_queue', 'task.claim', 'task.release', 'task.complete',
            'task.block', 'task.unblock', 'task.comment', 'task.create_adhoc',
            'sample.view', 'formula.view', 'site.view',
            'site.visit_create', 'site.visit_submit', 'site.set_readiness',
            'site.corrective_action_manage', 'site.measurements_edit',
            'time.timer_run', 'time.timer_run_for_others', 'time.crew_log_submit', 'time.correct',
            'employee.manage',
        ],
        'viewer' => [
            'project.view', 'project.view_all',
            'task.view_all',
            'payment.view', 'journal.view',
            'sample.view', 'formula.view', 'site.view', 'time.view_all',
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::DEPARTMENTS as $code => $name) {
            Department::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'is_queue' => true, 'active' => true],
            );
        }

        $this->seedBlockerCategories();

        foreach (self::PERMISSIONS as $permission) {
            Permission::updateOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $superAdmin = Role::updateOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions(Permission::all());

        foreach (self::ROLE_PERMISSIONS as $roleName => $permissions) {
            $role = Role::updateOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($permissions);
        }

        $settings = [
            'work_start' => '09:00',
            'work_end' => '17:00',
            'weekend_days' => ['friday'],
            'default_locale' => 'en',
            'humidity_max' => 85,
            'sample_repeat_attempt_threshold' => 4,
            'block_all_when_site_not_ready' => false,
        ];

        foreach ($settings as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'group' => 'general'],
            );
        }

        $this->call(PaymentWorkflowSeeder::class);
        $this->call(SiteChecklistSeeder::class);
    }

    private function seedBlockerCategories(): void
    {
        $site = Department::query()->where('code', 'site')->first();
        $workshop = Department::query()->where('code', 'workshop')->first();

        $categories = [
            [
                'code' => 'site_not_ready',
                'name' => ['en' => 'Site not ready', 'ar' => 'الموقع غير جاهز'],
                'requires_expected_date' => false,
                'notifies_department_id' => $site?->id,
            ],
            [
                'code' => 'missing_material',
                'name' => ['en' => 'Missing material', 'ar' => 'مواد ناقصة'],
                'requires_expected_date' => false,
                'notifies_department_id' => $workshop?->id,
            ],
            [
                'code' => 'waiting_client',
                'name' => ['en' => 'Waiting for client', 'ar' => 'في انتظار العميل'],
                'requires_expected_date' => true,
                'notifies_department_id' => null,
            ],
            [
                'code' => 'technical_problem',
                'name' => ['en' => 'Technical problem', 'ar' => 'مشكلة فنية'],
                'requires_expected_date' => false,
                'notifies_department_id' => null,
            ],
        ];

        foreach ($categories as $category) {
            BlockerCategory::updateOrCreate(
                ['code' => $category['code']],
                $category,
            );
        }
    }
}
