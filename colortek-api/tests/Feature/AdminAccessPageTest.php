<?php

declare(strict_types=1);

use App\Models\User;
use App\Policies\RolePolicy;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->seed(ReferenceSeeder::class);
});

function actingAsSuperAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    Sanctum::actingAs($user);

    return $user;
}

it('binds RolePolicy even though Role lives outside App\Models', function (): void {
    // Laravel only auto-discovers App\Policies\X for App\Models\X, so without an
    // explicit Gate::policy() the role screens 403 for everyone.
    expect(Gate::getPolicyFor(Role::class))->toBeInstanceOf(RolePolicy::class);
});

it('lists roles for a super admin', function (): void {
    actingAsSuperAdmin();

    $this->getJson('/api/v1/admin/roles')
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'name', 'permissions_count', 'users_count', 'is_protected']]]);
});

it('counts the users holding each role', function (): void {
    // Regression: Role::users() resolves the user model from the active guard.
    // Under Sanctum that guard had no provider, so this 500'd on every request
    // while passing in tinker under the web guard.
    $admin = actingAsSuperAdmin();

    $response = $this->getJson('/api/v1/admin/roles')->assertOk();
    $role = collect($response->json('data'))->firstWhere('name', 'super_admin');

    expect($role['users_count'])->toBe(1)
        ->and($admin->hasRole('super_admin'))->toBeTrue();
});

it('marks super_admin as protected', function (): void {
    actingAsSuperAdmin();

    $response = $this->getJson('/api/v1/admin/roles');
    $role = collect($response->json('data'))->firstWhere('name', 'super_admin');

    expect($role['is_protected'])->toBeTrue();
});

it('hides roles from a user without role.manage', function (): void {
    $user = User::factory()->inDepartment('sales')->create();
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/admin/roles')->assertNotFound();
});

it('creates a role with permissions', function (): void {
    actingAsSuperAdmin();

    $this->postJson('/api/v1/admin/roles', [
        'name' => 'estimator',
        'permissions' => ['project.view', 'quotation.manage'],
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'estimator')
        ->assertJsonPath('data.permissions_count', 2);
});

it('refuses to rename the super_admin role', function (): void {
    actingAsSuperAdmin();
    // Roles are stored under the web guard; Sanctum makes 'sanctum' the default.
    $superAdmin = Role::findByName('super_admin', 'web');

    $this->patchJson("/api/v1/admin/roles/{$superAdmin->id}", ['name' => 'hijacked'])
        ->assertForbidden();
});

it('creates a user and assigns roles', function (): void {
    actingAsSuperAdmin();

    $response = $this->postJson('/api/v1/admin/users', [
        'name' => 'New Estimator',
        'email' => 'estimator@colortek.test',
        'password' => 'password123',
        'roles' => ['sales'],
    ])->assertCreated();

    expect($response->json('data.roles'))->toContain('sales');
    $this->assertDatabaseHas('users', ['email' => 'estimator@colortek.test']);
});

it('serves every tab the access screen loads', function (): void {
    actingAsSuperAdmin();

    $this->getJson('/api/v1/admin/roles')->assertOk();
    $this->getJson('/api/v1/admin/users')->assertOk();
    $this->getJson('/api/v1/admin/employees')->assertOk();
    $this->getJson('/api/v1/admin/permissions')->assertOk();
    $this->getJson('/api/v1/admin/access/coverage')->assertOk();
});
