<?php

declare(strict_types=1);

namespace App\Support;

final class PermissionCatalog
{
    /** @var list<string> */
    public const DANGEROUS = [
        'site.override_block',
        'payment.skip_proof',
        'journal.reopen',
        'formula.update_registered',
        'time.correct',
        'task.override_deadline',
        'audit.view',
    ];

    /** @var list<string> */
    public const COVERAGE_CHECKS = [
        'sample.approve_manager',
    ];

    /** @return list<array{group: string, permissions: list<array{name: string, description: string, dangerous: bool}>}> */
    public static function grouped(): array
    {
        return collect(self::definitions())
            ->groupBy('group')
            ->map(fn ($items, $group) => [
                'group' => (string) $group,
                'permissions' => $items->map(fn ($item) => [
                    'name' => $item['name'],
                    'description' => $item['description'],
                    'dangerous' => in_array($item['name'], self::DANGEROUS, true),
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /** @return list<string> */
    public static function allNames(): array
    {
        return array_column(self::definitions(), 'name');
    }

    public static function description(string $permission): string
    {
        foreach (self::definitions() as $definition) {
            if ($definition['name'] === $permission) {
                return $definition['description'];
            }
        }

        return $permission;
    }

    /** @return list<array{group: string, name: string, description: string}> */
    private static function definitions(): array
    {
        return [
            ['group' => 'Projects', 'name' => 'project.view', 'description' => 'See projects assigned to you'],
            ['group' => 'Projects', 'name' => 'project.view_all', 'description' => 'See all projects'],
            ['group' => 'Projects', 'name' => 'project.create', 'description' => 'Create projects'],
            ['group' => 'Projects', 'name' => 'project.update', 'description' => 'Edit project details'],
            ['group' => 'Projects', 'name' => 'project.change_stage', 'description' => 'Move projects between stages'],
            ['group' => 'Projects', 'name' => 'project.complete', 'description' => 'Mark projects complete'],
            ['group' => 'Projects', 'name' => 'project.cancel', 'description' => 'Cancel projects'],
            ['group' => 'Tasks', 'name' => 'task.view_own_queue', 'description' => 'See your department queue'],
            ['group' => 'Tasks', 'name' => 'task.view_all', 'description' => 'See every task'],
            ['group' => 'Tasks', 'name' => 'task.claim', 'description' => 'Claim tasks from a queue'],
            ['group' => 'Tasks', 'name' => 'task.release', 'description' => 'Release claimed tasks'],
            ['group' => 'Tasks', 'name' => 'task.complete', 'description' => 'Complete tasks'],
            ['group' => 'Tasks', 'name' => 'task.block', 'description' => 'Block tasks'],
            ['group' => 'Tasks', 'name' => 'task.unblock', 'description' => 'Clear blockers'],
            ['group' => 'Tasks', 'name' => 'task.comment', 'description' => 'Comment on tasks'],
            ['group' => 'Tasks', 'name' => 'task.reassign', 'description' => 'Reassign tasks'],
            ['group' => 'Tasks', 'name' => 'task.create_adhoc', 'description' => 'Create ad-hoc tasks'],
            ['group' => 'Tasks', 'name' => 'task.cancel', 'description' => 'Cancel tasks'],
            ['group' => 'Tasks', 'name' => 'task.override_deadline', 'description' => 'Override a task deadline'],
            ['group' => 'Payments and journal', 'name' => 'payment.view', 'description' => 'View payments'],
            ['group' => 'Payments and journal', 'name' => 'payment.confirm', 'description' => 'Confirm payments received'],
            ['group' => 'Payments and journal', 'name' => 'payment.review', 'description' => 'Review payments'],
            ['group' => 'Payments and journal', 'name' => 'payment.skip_proof', 'description' => 'Skip mandatory payment proof'],
            ['group' => 'Payments and journal', 'name' => 'journal.view', 'description' => 'View journals'],
            ['group' => 'Payments and journal', 'name' => 'journal.prepare', 'description' => 'Prepare daily journals'],
            ['group' => 'Payments and journal', 'name' => 'journal.account', 'description' => 'Process journals in accounting'],
            ['group' => 'Payments and journal', 'name' => 'journal.reopen', 'description' => 'Reopen submitted journals'],
            ['group' => 'Samples and formula', 'name' => 'sample.view', 'description' => 'View samples'],
            ['group' => 'Samples and formula', 'name' => 'sample.create', 'description' => 'Create sample requests'],
            ['group' => 'Samples and formula', 'name' => 'sample.create_presale', 'description' => 'Create pre-sale samples'],
            ['group' => 'Samples and formula', 'name' => 'sample.approve_manager', 'description' => 'Approve samples as manager'],
            ['group' => 'Samples and formula', 'name' => 'sample.record_client_decision', 'description' => 'Record client sample decisions'],
            ['group' => 'Samples and formula', 'name' => 'sample.request_modification', 'description' => 'Request sample modifications'],
            ['group' => 'Samples and formula', 'name' => 'sample.cancel', 'description' => 'Cancel samples'],
            ['group' => 'Samples and formula', 'name' => 'formula.view', 'description' => 'View formulas'],
            ['group' => 'Samples and formula', 'name' => 'formula.author', 'description' => 'Author tinting recipes'],
            ['group' => 'Samples and formula', 'name' => 'formula.register', 'description' => 'Register formulas'],
            ['group' => 'Samples and formula', 'name' => 'formula.update_registered', 'description' => 'Correct registered formulas'],
            ['group' => 'Site', 'name' => 'site.view', 'description' => 'View site visits'],
            ['group' => 'Site', 'name' => 'site.visit_create', 'description' => 'Start site visit reports'],
            ['group' => 'Site', 'name' => 'site.visit_submit', 'description' => 'Submit site visit reports'],
            ['group' => 'Site', 'name' => 'site.set_readiness', 'description' => 'Set site readiness'],
            ['group' => 'Site', 'name' => 'site.override_block', 'description' => 'Start work while site is not ready'],
            ['group' => 'Site', 'name' => 'site.corrective_action_manage', 'description' => 'Manage corrective actions'],
            ['group' => 'Site', 'name' => 'site.measurements_edit', 'description' => 'Edit submitted measurements'],
            ['group' => 'Time', 'name' => 'time.timer_run', 'description' => 'Run workshop timers'],
            ['group' => 'Time', 'name' => 'time.timer_run_for_others', 'description' => 'Run timers for other employees'],
            ['group' => 'Time', 'name' => 'time.crew_log_submit', 'description' => 'Submit site crew logs'],
            ['group' => 'Time', 'name' => 'time.correct', 'description' => 'Correct recorded time entries'],
            ['group' => 'Time', 'name' => 'time.view_all', 'description' => 'View all time entries'],
            ['group' => 'Administration', 'name' => 'user.manage', 'description' => 'Manage users'],
            ['group' => 'Administration', 'name' => 'role.manage', 'description' => 'Manage roles and permissions'],
            ['group' => 'Administration', 'name' => 'role.assign', 'description' => 'Assign roles to users'],
            ['group' => 'Administration', 'name' => 'holiday.manage', 'description' => 'Manage holidays'],
            ['group' => 'Administration', 'name' => 'employee.manage', 'description' => 'Manage employees'],
            ['group' => 'Administration', 'name' => 'workflow.view', 'description' => 'View workflow templates'],
            ['group' => 'Administration', 'name' => 'workflow.manage', 'description' => 'Edit and publish workflow templates'],
            ['group' => 'Administration', 'name' => 'settings.manage', 'description' => 'Manage calendar and settings'],
            ['group' => 'Administration', 'name' => 'audit.view', 'description' => 'View audit logs'],
            ['group' => 'Administration', 'name' => 'client.manage', 'description' => 'Manage clients'],
            ['group' => 'Administration', 'name' => 'quotation.manage', 'description' => 'Manage quotations'],
        ];
    }
}
