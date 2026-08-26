<?php

declare(strict_types=1);

use App\Enums\TaskStatus;
use App\Models\BlockerCategory;
use App\Models\Department;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    $this->seed(ReferenceSeeder::class);
});

it('rejects unauthenticated API requests with 401', function (): void {
    $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    $this->getJson('/api/v1/tasks')->assertUnauthorized();
});

it('logs in with valid credentials and returns a bearer token', function (): void {
    $user = User::factory()->create([
        'email' => 'sales@colortek.test',
        'password' => Hash::make('secret'),
    ]);
    $user->assignRole('sales');

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'sales@colortek.test',
        'password' => 'secret',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'email', 'permissions']]]);

    expect($response->json('data.token'))->not->toBeEmpty();
});

it('rejects login with invalid credentials', function (): void {
    User::factory()->create(['email' => 'sales@colortek.test']);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'sales@colortek.test',
        'password' => 'wrong-password',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('logs out and invalidates the current token', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('api')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/auth/logout')
        ->assertOk();

    expect($user->tokens()->count())->toBe(0);

    $this->app['auth']->forgetGuards();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/auth/me')
        ->assertUnauthorized();
});

it('returns enum catalogs with value and label pairs', function (): void {
    $user = User::factory()->inDepartment('sales')->create();
    $token = $user->createToken('api')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/enums/task_status')
        ->assertOk();

    $statuses = $response->json('data');

    expect($statuses)->toBeArray()->not->toBeEmpty()
        ->and($statuses[0])->toHaveKeys(['value', 'label']);

    $response->assertJsonFragment(['value' => 'ready']);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/enums/task_priority')
        ->assertOk();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/enums/blocker_category')
        ->assertOk()
        ->assertJsonPath('data.0.value', 'missing_material');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/enums/unknown_enum')
        ->assertNotFound();
});

it('runs the full task lifecycle through HTTP endpoints', function (): void {
    $department = Department::query()->where('code', 'sales')->first();
    $user = User::factory()->inDepartment('sales')->create();
    $token = $user->createToken('api')->plainTextToken;

    $task = Task::factory()->ready()->create(['department_id' => $department->id]);
    $headers = ['Authorization' => "Bearer {$token}"];

    $this->withHeaders($headers)
        ->getJson("/api/v1/tasks/{$task->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $task->id)
        ->assertJsonPath('data.status', 'ready');

    $this->withHeaders($headers)
        ->postJson("/api/v1/tasks/{$task->id}/claim")
        ->assertOk()
        ->assertJsonPath('data.status', 'claimed');

    $this->withHeaders($headers)
        ->postJson("/api/v1/tasks/{$task->id}/start")
        ->assertOk()
        ->assertJsonPath('data.status', 'in_progress');

    $this->withHeaders($headers)
        ->postJson("/api/v1/tasks/{$task->id}/pause")
        ->assertOk()
        ->assertJsonPath('data.status', 'paused');

    $this->withHeaders($headers)
        ->postJson("/api/v1/tasks/{$task->id}/start")
        ->assertOk()
        ->assertJsonPath('data.status', 'in_progress');

    $category = BlockerCategory::query()->where('code', 'technical_problem')->first();

    $this->withHeaders($headers)
        ->postJson("/api/v1/tasks/{$task->id}/block", [
            'blocker_category_id' => $category->id,
            'reason' => 'Waiting for specifications',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'blocked');

    $this->withHeaders($headers)
        ->postJson("/api/v1/tasks/{$task->id}/start")
        ->assertOk()
        ->assertJsonPath('data.status', 'in_progress');

    $this->withHeaders($headers)
        ->postJson("/api/v1/tasks/{$task->id}/complete", ['fields' => [], 'attachment_ids' => []])
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');

    expect($task->fresh()->status)->toBe(TaskStatus::Completed);
});

it('releases a claimed task back to the queue via HTTP', function (): void {
    $user = User::factory()->inDepartment('sales')->create();
    $task = Task::factory()->claimed($user)->create();
    $token = $user->createToken('api')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/tasks/{$task->id}/release")
        ->assertOk()
        ->assertJsonPath('data.status', 'ready');
});

it('returns 403 when the user lacks permission', function (): void {
    $viewer = User::factory()->create();
    $viewer->assignRole('viewer');
    $token = $viewer->createToken('api')->plainTextToken;

    $task = Task::factory()->ready()->create();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/tasks/{$task->id}/claim")
        ->assertForbidden();
});

it('returns 404 for tasks the user cannot see', function (): void {
    $sales = User::factory()->inDepartment('sales')->create();
    $token = $sales->createToken('api')->plainTextToken;
    $accountingDept = Department::query()->where('code', 'accounting')->first();

    $hidden = Task::factory()->ready()->create([
        'project_id' => Project::factory()->create()->id,
        'department_id' => $accountingDept->id,
    ]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/tasks/{$hidden->id}")
        ->assertNotFound();
});
