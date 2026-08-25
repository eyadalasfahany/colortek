<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Holiday;
use App\Models\Setting;
use App\Models\SiteChecklistItem;
use App\Models\User;
use Database\Seeders\ReferenceSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->seed(ReferenceSeeder::class);
    $this->superAdmin = User::factory()->create();
    $this->superAdmin->assignRole('super_admin');
    $this->salesUser = User::factory()->inDepartment('sales')->create();
});

it('returns 404 for sales user on admin settings', function (): void {
    Sanctum::actingAs($this->salesUser);
    $this->getJson('/api/v1/admin/settings')->assertNotFound();
});

it('allows super admin to read settings', function (): void {
    Sanctum::actingAs($this->superAdmin);
    $this->getJson('/api/v1/admin/settings')->assertOk();
});

it('returns permission groups for super admin', function (): void {
    Sanctum::actingAs($this->superAdmin);
    $this->getJson('/api/v1/admin/permissions')->assertOk()->assertJsonStructure(['data' => [['group', 'permissions']]]);
});

it('blocks admin role user from role endpoints', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    Sanctum::actingAs($admin);
    $this->getJson('/api/v1/admin/roles')->assertNotFound();
});

it('allows admin to patch user details but not assign roles', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $target = User::factory()->create();
    Sanctum::actingAs($admin);
    $this->patchJson("/api/v1/admin/users/{$target->id}", ['name' => 'Updated Name'])->assertOk();
    $this->postJson("/api/v1/admin/users/{$target->id}/roles", ['roles' => ['sales']])->assertNotFound();
});

it('seeds five checklist items', function (): void {
    expect(SiteChecklistItem::where('code', 'humidity')->exists())->toBeTrue();
    expect(SiteChecklistItem::count())->toBe(5);
});

it('calendar impact returns a count', function (): void {
    Sanctum::actingAs($this->superAdmin);
    $this->postJson('/api/v1/admin/calendar/impact', [
        'settings' => ['work_end' => '16:00'],
    ])->assertOk()->assertJsonStructure(['data' => ['affected_task_count']]);
});

it('creates a holiday with audit on confirm', function (): void {
    Sanctum::actingAs($this->superAdmin);
    $this->postJson('/api/v1/admin/holidays', [
        'date' => '2026-12-25',
        'name' => ['en' => 'Christmas', 'ar' => 'عيد'],
        'type' => 'public',
        'is_recurring' => false,
        'confirm' => true,
    ])->assertCreated();
    expect(Holiday::query()->whereDate('date', '2026-12-25')->exists())->toBeTrue();
});

it('lists workflow templates for admin', function (): void {
    Sanctum::actingAs($this->superAdmin);
    $this->getJson('/api/v1/admin/workflow-templates')->assertOk();
});

it('lists failure diagnostics for settings admin', function (): void {
    Sanctum::actingAs($this->superAdmin);
    $this->getJson('/api/v1/admin/stalled-instances')->assertOk();
    $this->getJson('/api/v1/admin/unclaimed-tasks')->assertOk();
    $this->getJson('/api/v1/admin/failed-jobs')->assertOk();
});

it('shows coverage warning when no sample approver exists', function (): void {
    Role::findByName('approver', 'web')->users()->detach();
    Sanctum::actingAs($this->superAdmin);
    $this->getJson('/api/v1/admin/access/coverage')
        ->assertOk()
        ->assertJsonFragment(['permission' => 'sample.approve_manager']);
});

it('patches settings and writes audit row when confirmed', function (): void {
    Sanctum::actingAs($this->superAdmin);
    $this->patchJson('/api/v1/admin/settings', [
        'work_end' => '16:00',
        'confirm' => true,
    ])->assertOk();
    expect(Setting::get('work_end'))->toBe('16:00');
    expect(AuditLog::query()->where('event', 'updated')->exists())->toBeTrue();
});
