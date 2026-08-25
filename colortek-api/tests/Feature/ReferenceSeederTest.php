<?php

declare(strict_types=1);

use App\Models\Department;
use App\Models\Setting;
use Database\Seeders\ReferenceSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('seeds the eight departments', function (): void {
    $this->seed(ReferenceSeeder::class);

    expect(Department::count())->toBe(8)
        ->and(Department::where('code', 'workshop')->exists())->toBeTrue();
});

it('seeds every role from the permission matrix', function (): void {
    $this->seed(ReferenceSeeder::class);

    expect(Role::pluck('name')->all())->toContain(
        'super_admin', 'admin', 'management', 'approver', 'sales',
        'reception', 'accounting', 'workshop_supervisor', 'tinting',
        'site_engineer', 'viewer',
    );
});

it('grants super_admin every permission', function (): void {
    $this->seed(ReferenceSeeder::class);

    $super = Role::findByName('super_admin');

    expect($super->permissions()->count())->toBe(Permission::count());
});

it('grants payment.skip_proof to nobody', function (): void {
    $this->seed(ReferenceSeeder::class);

    $permission = Permission::findByName('payment.skip_proof');

    expect($permission->roles()->where('name', '!=', 'super_admin')->count())->toBe(0);
});

it('seeds the confirmed shift hours', function (): void {
    $this->seed(ReferenceSeeder::class);

    expect(Setting::get('work_start'))->toBe('09:00')
        ->and(Setting::get('work_end'))->toBe('17:00')
        ->and(Setting::get('weekend_days'))->toBe(['friday']);
});
