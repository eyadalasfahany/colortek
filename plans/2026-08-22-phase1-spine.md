# Colortek Phase 1 — The Spine — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A Laravel API in which completing a task automatically creates the next task in the correct department queue, with correct deadlines computed through the company working calendar — proven by an end-to-end test.

**Architecture:** Laravel 13 API behind Sanctum tokens. Business logic in Services, persistence in Repositories, output in Resources, validation in Form Requests. The workflow engine is the only thing that creates tasks; it runs synchronously inside the same transaction as the task completion that triggered it. State changes emit events; activity and audit rows are written by listeners.

**Tech Stack:** PHP 8.4, Laravel 13.26, MySQL 9.2, Pest, Sanctum, `spatie/laravel-permission`, `spatie/laravel-translatable`, Laravel Boost (MCP, dev only).

**Spec:** `specs/` — read `specs/README.md` first. The sections each task depends on are named in that task.

## Global Constraints

Copied verbatim from the spec. Every task's requirements implicitly include these.

- `declare(strict_types=1);` at the top of every PHP file we own, including tests. — `specs/15-engineering-standards.md` §A3
- Layer flow is `Form Request → thin Controller → Service → Repository → Resource`. No DTO or UseCase layers. — §A1
- Controllers contain no business logic, no repository calls, and no `if` branching on data. — §A3
- Validation lives only in Form Requests. Never in a controller or a service. — §A3
- Friendly 404s: `throw new ModelNotFoundException(__('Task not found'));`. Never Laravel's default message. — §A3
- Resources list fields explicitly. Never `$model->toArray()`. No `created_at` / `updated_at` / `deleted_at` unless the contract needs them. — §A3
- Every list endpoint returns a paginator, `per_page` default **15**. Only `/options/*` and `/enums/*` return a plain collection. — §A7
- Every relation a resource touches is eager-loaded in the service or repository. Resources gate relations with `whenLoaded()`. Never lazy-load in a loop. — §A3
- Multi-write operations are wrapped in `DB::transaction()`. — §A3
- Authorisation is checked as a **permission**, never a role name, and lives in Policies. — `specs/04-permissions-and-roles.md`
- All timestamps stored UTC. Application timezone `Africa/Cairo`. — `specs/03-data-model.md`
- Money is `decimal(14,2)` with a separate `currency` char(3), default `EGP`. Never float. — `specs/03-data-model.md`
- Translatable text uses JSON columns via `spatie/laravel-translatable`, not `*_en` / `*_ar` column pairs. Two exceptions: `activity_events.message_en` / `message_ar`, and the `tasks` title snapshot. — §A4
- The shift is **09:00–17:00**, weekend is **Friday**. One working day = **8 hours**. — `specs/01` A14, A14b
- Tests ship with every change. Feature tests cover HTTP contracts; unit tests cover services with Mockery-mocked repositories. Never mock the business rule under test. — §A9
- Before any task is called done: `php artisan test` passes and `composer qa` passes clean. — §A10

**Working directory for all commands:** `/Applications/MAMP/htdocs/Colortek/colortek-api`

---

## File Structure

Created across this plan. Each file has one responsibility.

```text
colortek-api/
  app/
    Enums/
      TaskStatus.php              the 9 task statuses + allowed transitions
      TaskPriority.php
      BlockerCategoryCode.php
      HolidayType.php
    Models/
      User.php  Department.php  Employee.php  Holiday.php  Setting.php
      BlockerCategory.php  Task.php  TaskFieldValue.php  TaskStatusEvent.php
      TaskDependency.php  TimeEntry.php
      WorkflowTemplate.php  WorkflowTaskDefinition.php  WorkflowTransition.php
      WorkflowInstance.php  WorkflowTransitionLog.php
      ActivityEvent.php  AuditLog.php
    Repositories/
      BaseRepository.php          shared find/paginate/create/update
      TaskRepository.php  WorkflowRepository.php  HolidayRepository.php
    Services/
      Time/WorkingCalendar.php    the only place that knows about shifts and holidays
      Tasks/DeadlineCalculator.php
      Tasks/TaskService.php       claim, release, start, pause, block, complete
      Tasks/TaskValidator.php     required fields and attachments before complete
      Time/TimerService.php
      Workflow/WorkflowEngine.php resolve transitions, create successors
      Workflow/ConditionEvaluator.php
      Workflow/TaskFactory.php    build a Task from a WorkflowTaskDefinition
      Activity/ActivityRecorder.php
    Exceptions/
      InvalidTaskTransition.php  TaskAlreadyClaimed.php  TaskNotReadyToComplete.php
    Events/
      TaskCreated.php  TaskClaimed.php  TaskCompleted.php  TaskBlocked.php
    Listeners/
      RecordTaskActivity.php
    Http/
      Controllers/Api/V1/  AuthController  TaskController  EnumController
      Requests/            TaskCompleteRequest  TaskBlockRequest
      Resources/           TaskResource  TaskListResource  UserResource
      Filters/             TaskFilter
    Policies/              TaskPolicy.php
  database/
    migrations/            one per table group
    factories/             UserFactory DepartmentFactory TaskFactory WorkflowTemplateFactory
    seeders/               ReferenceSeeder (departments, roles, permissions, blocker categories, settings)
  tests/
    Unit/                  WorkingCalendarTest DeadlineCalculatorTest ConditionEvaluatorTest
    Feature/               AuthTest TaskLifecycleTest TaskClaimRaceTest WorkflowEngineTest SpineEndToEndTest
```

---

### Task 1: Configure the Laravel app to company standards

The app has already been created at `colortek-api/` with Laravel 13.26 and Boost installed. This task makes it match `specs/15-engineering-standards.md` before any feature code exists.

**Files:**
- Modify: `colortek-api/composer.json`
- Create: `colortek-api/phpstan.neon`
- Create: `colortek-api/pint.json`
- Modify: `colortek-api/config/app.php`
- Modify: `colortek-api/.env`, `colortek-api/.env.example`
- Modify: `colortek-api/tests/Pest.php`

**Interfaces:**
- Consumes: nothing
- Produces: a green `composer qa` command that every later task runs

- [ ] **Step 1: Install the required packages**

```bash
composer require spatie/laravel-permission spatie/laravel-translatable
composer require --dev pestphp/pest pestphp/pest-plugin-laravel larastan/larastan laravel/pint mockery/mockery
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
```

- [ ] **Step 2: Point the app at MySQL and Cairo time**

Set in `.env` and mirror the non-secret keys in `.env.example`:

```ini
APP_TIMEZONE=Africa/Cairo
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=colortek
DB_USERNAME=root
DB_PASSWORD=
QUEUE_CONNECTION=database
```

Create the database:

```bash
mysql -u root -e "CREATE DATABASE IF NOT EXISTS colortek CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

`utf8mb4` is required, not optional — Arabic in plain `utf8` truncates mixed content. See `specs/12-i18n-and-rtl.md` §2.

- [ ] **Step 3: Add the static analysis config**

Create `phpstan.neon`:

```neon
includes:
    - vendor/larastan/larastan/extension.neon
parameters:
    paths:
        - app
        - database
        - tests
    level: 6
    checkMissingIterableValueType: false
```

Create `pint.json`:

```json
{
    "preset": "laravel",
    "rules": {
        "declare_strict_types": true,
        "ordered_imports": { "sort_algorithm": "alpha" }
    }
}
```

- [ ] **Step 4: Add the qa script**

Add to the `scripts` block of `composer.json`:

```json
"qa": [
    "@php vendor/bin/pint --test",
    "@php vendor/bin/phpstan analyse --memory-limit=1G",
    "@php artisan test"
]
```

- [ ] **Step 5: Verify the toolchain runs**

Run: `composer qa`
Expected: Pint reports no style issues, PHPStan reports no errors, and the default Laravel test suite passes. If Pint fails on Laravel's own stub files, run `vendor/bin/pint` once to fix them, then re-run.

- [ ] **Step 6: Commit**

```bash
git add colortek-api
git commit -m "chore: scaffold Laravel API with company standards toolchain"
```

---

### Task 2: Core people tables and the reference seeder

**Spec:** `specs/03-data-model.md` §2, `specs/04-permissions-and-roles.md` §2–§3, `specs/13-odoo-gateway-and-seed-data.md` §3.1

**Files:**
- Create: `database/migrations/*_create_departments_table.php`
- Create: `database/migrations/*_create_employees_table.php`
- Create: `database/migrations/*_create_department_user_table.php`
- Create: `database/migrations/*_create_settings_table.php`
- Modify: `database/migrations/0001_01_01_000000_create_users_table.php`
- Create: `app/Models/Department.php`, `app/Models/Employee.php`, `app/Models/Setting.php`
- Modify: `app/Models/User.php`
- Create: `database/seeders/ReferenceSeeder.php`
- Test: `tests/Feature/ReferenceSeederTest.php`

**Interfaces:**
- Consumes: Task 1's configured app
- Produces: `Department::class` with `code` and translatable `name`; `User::departments()` and `User::isSupervisorOf(Department $d): bool`; `Setting::get(string $key): mixed`; eleven seeded roles and the full permission list

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ReferenceSeederTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\Department;
use App\Models\Setting;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('seeds the eight departments', function (): void {
    $this->seed(\Database\Seeders\ReferenceSeeder::class);

    expect(Department::count())->toBe(8)
        ->and(Department::where('code', 'workshop')->exists())->toBeTrue();
});

it('seeds every role from the permission matrix', function (): void {
    $this->seed(\Database\Seeders\ReferenceSeeder::class);

    expect(Role::pluck('name')->all())->toContain(
        'super_admin', 'admin', 'management', 'approver', 'sales',
        'reception', 'accounting', 'workshop_supervisor', 'tinting',
        'site_engineer', 'viewer',
    );
});

it('grants super_admin every permission', function (): void {
    $this->seed(\Database\Seeders\ReferenceSeeder::class);

    $super = Role::findByName('super_admin');

    expect($super->permissions()->count())->toBe(Permission::count());
});

it('grants payment.skip_proof to nobody', function (): void {
    $this->seed(\Database\Seeders\ReferenceSeeder::class);

    $permission = Permission::findByName('payment.skip_proof');

    expect($permission->roles()->where('name', '!=', 'super_admin')->count())->toBe(0);
});

it('seeds the confirmed shift hours', function (): void {
    $this->seed(\Database\Seeders\ReferenceSeeder::class);

    expect(Setting::get('work_start'))->toBe('09:00')
        ->and(Setting::get('work_end'))->toBe('17:00')
        ->and(Setting::get('weekend_days'))->toBe(['friday']);
});
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --filter=ReferenceSeederTest`
Expected: FAIL — `Class "App\Models\Department" not found`.

- [ ] **Step 3: Write the migrations**

`departments`:

```php
Schema::create('departments', function (Blueprint $table): void {
    $table->id();
    $table->string('code', 30)->unique();
    $table->json('name');
    $table->boolean('is_queue')->default(true);
    $table->boolean('active')->default(true);
    $table->timestamps();
    $table->softDeletes();
});
```

`employees`:

```php
Schema::create('employees', function (Blueprint $table): void {
    $table->id();
    $table->string('code', 30)->unique();
    $table->string('name', 150);
    $table->foreignId('department_id')->constrained();
    $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
    $table->boolean('active')->default(true);
    $table->string('odoo_employee_id', 50)->nullable();
    $table->timestamps();
    $table->softDeletes();
});
```

`department_user`:

```php
Schema::create('department_user', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('department_id')->constrained()->cascadeOnDelete();
    $table->boolean('is_supervisor')->default(false);
    $table->unique(['user_id', 'department_id']);
});
```

`settings`:

```php
Schema::create('settings', function (Blueprint $table): void {
    $table->string('key', 80)->primary();
    $table->json('value');
    $table->string('group', 40)->default('general');
    $table->timestamps();
});
```

Add to the existing users migration, inside the `users` table definition:

```php
$table->string('phone', 30)->nullable();
$table->enum('locale', ['en', 'ar'])->default('en');
$table->foreignId('primary_department_id')->nullable()->constrained('departments')->nullOnDelete();
$table->boolean('active')->default(true);
$table->timestamp('last_seen_at')->nullable();
```

Because `users` is migrated before `departments`, move the `primary_department_id` foreign key into a separate later migration rather than reordering Laravel's own file.

- [ ] **Step 4: Write the models**

`app/Models/Department.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

final class Department extends Model
{
    use HasFactory;
    use HasTranslations;
    use SoftDeletes;

    public array $translatable = ['name'];

    protected $fillable = ['code', 'name', 'is_queue', 'active'];

    protected function casts(): array
    {
        return ['is_queue' => 'boolean', 'active' => 'boolean'];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('is_supervisor');
    }
}
```

`app/Models/Setting.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class Setting extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'value', 'group'];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $row = static::find($key);

        return $row === null ? $default : $row->value;
    }
}
```

`Setting::get()` returns the decoded JSON value. A scalar setting such as
`work_start` is stored as the JSON string `"09:00"` and comes back as a PHP
string; `weekend_days` is stored as a JSON array and comes back as an array.

Add to `User`:

```php
public function departments(): BelongsToMany
{
    return $this->belongsToMany(Department::class)->withPivot('is_supervisor');
}

public function isSupervisorOf(Department $department): bool
{
    return $this->departments()
        ->wherePivot('department_id', $department->id)
        ->wherePivot('is_supervisor', true)
        ->exists();
}
```

Add `use Spatie\Permission\Traits\HasRoles;` to `User`.

- [ ] **Step 5: Write the seeder**

`database/seeders/ReferenceSeeder.php` seeds, in this order:

1. The eight departments from `specs/00-overview-and-glossary.md` §3: `sales`, `reception`, `accounting`, `workshop`, `tinting`, `site`, `management`, `admin`. Each with `name` as `['en' => 'Workshop', 'ar' => 'الورشة']`.
2. Every permission listed in `specs/04-permissions-and-roles.md` §2. Copy the list from that file — all 61 of them. Do not invent or omit any.
3. The eleven roles from §3, each syncing exactly the permissions marked `•` in the matrix in that section. `super_admin` syncs all permissions. `payment.skip_proof` is attached to `super_admin` only.
4. The settings from `specs/13-odoo-gateway-and-seed-data.md` §3.1, including `work_start` `"09:00"`, `work_end` `"17:00"`, `weekend_days` `["friday"]`.

The seeder must be idempotent — use `updateOrCreate` keyed on `code` / `name` / `key` — because it is re-run on every deployment.

**Do not seed holidays.** `specs/13` §3.1 — an admin adds them through a screen, and guessing a national holiday list produces silently wrong deadlines.

- [ ] **Step 6: Run the tests to verify they pass**

Run: `php artisan test --filter=ReferenceSeederTest`
Expected: 5 passing tests.

- [ ] **Step 7: Commit**

```bash
git add colortek-api
git commit -m "feat: departments, employees, settings and the reference seeder"
```

---

### Task 3: The working calendar

**Spec:** `specs/06-task-and-time-tracking.md` §6, `specs/01` A14/A14b/A14c, `specs/09-screens/06-admin-calendar-and-holidays.md`

This is pure logic with no HTTP surface. Every deadline in the system depends on it, and the Friday-plus-holiday date maths is where bugs hide, so it is built and tested first.

**Files:**
- Create: `database/migrations/*_create_holidays_table.php`
- Create: `app/Models/Holiday.php`
- Create: `app/Enums/HolidayType.php`
- Create: `app/Services/Time/WorkingCalendar.php`
- Test: `tests/Unit/WorkingCalendarTest.php`

**Interfaces:**
- Consumes: `Setting::get()` from Task 2
- Produces:
  - `WorkingCalendar::isWorkingTime(CarbonImmutable $at): bool`
  - `WorkingCalendar::addWorkingMinutes(CarbonImmutable $from, int $minutes): CarbonImmutable`
  - `WorkingCalendar::workingMinutesBetween(CarbonImmutable $a, CarbonImmutable $b): int`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/WorkingCalendarTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\Holiday;
use App\Models\Setting;
use App\Services\Time\WorkingCalendar;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    Setting::updateOrCreate(['key' => 'work_start'], ['value' => '09:00']);
    Setting::updateOrCreate(['key' => 'work_end'], ['value' => '17:00']);
    Setting::updateOrCreate(['key' => 'weekend_days'], ['value' => ['friday']]);

    $this->calendar = app(WorkingCalendar::class);
});

it('knows a Wednesday mid-morning is working time', function (): void {
    // 2026-09-02 is a Wednesday.
    expect($this->calendar->isWorkingTime(CarbonImmutable::parse('2026-09-02 10:00', 'Africa/Cairo')))
        ->toBeTrue();
});

it('knows before the shift starts is not working time', function (): void {
    expect($this->calendar->isWorkingTime(CarbonImmutable::parse('2026-09-02 08:59', 'Africa/Cairo')))
        ->toBeFalse();
});

it('knows Friday is not working time', function (): void {
    // 2026-09-04 is a Friday.
    expect($this->calendar->isWorkingTime(CarbonImmutable::parse('2026-09-04 10:00', 'Africa/Cairo')))
        ->toBeFalse();
});

it('adds working minutes inside one day', function (): void {
    $due = $this->calendar->addWorkingMinutes(
        CarbonImmutable::parse('2026-09-02 10:00', 'Africa/Cairo'), 120
    );

    expect($due->format('Y-m-d H:i'))->toBe('2026-09-02 12:00');
});

it('rolls a four hour deadline started at 15:00 on Thursday over the Friday weekend', function (): void {
    // 2026-09-03 is a Thursday. 2 hours left that day, 2 hours on Saturday.
    $due = $this->calendar->addWorkingMinutes(
        CarbonImmutable::parse('2026-09-03 15:00', 'Africa/Cairo'), 240
    );

    expect($due->format('Y-m-d H:i'))->toBe('2026-09-05 11:00');
});

it('skips a holiday as well as the weekend', function (): void {
    Holiday::create([
        'date' => '2026-09-05',
        'name' => ['en' => 'Test holiday', 'ar' => 'إجازة'],
        'type' => 'public',
        'is_recurring' => false,
    ]);

    $due = $this->calendar->addWorkingMinutes(
        CarbonImmutable::parse('2026-09-03 15:00', 'Africa/Cairo'), 240
    );

    expect($due->format('Y-m-d H:i'))->toBe('2026-09-06 11:00');
});

it('starts counting the next working morning when begun after the shift', function (): void {
    $due = $this->calendar->addWorkingMinutes(
        CarbonImmutable::parse('2026-09-02 19:00', 'Africa/Cairo'), 60
    );

    expect($due->format('Y-m-d H:i'))->toBe('2026-09-03 10:00');
});

it('counts one working day as eight hours', function (): void {
    $minutes = $this->calendar->workingMinutesBetween(
        CarbonImmutable::parse('2026-09-02 09:00', 'Africa/Cairo'),
        CarbonImmutable::parse('2026-09-03 09:00', 'Africa/Cairo'),
    );

    expect($minutes)->toBe(480);
});

it('applies a recurring holiday in a later year', function (): void {
    Holiday::create([
        'date' => '2025-09-05',
        'name' => ['en' => 'Recurring', 'ar' => 'متكرر'],
        'type' => 'company',
        'is_recurring' => true,
    ]);

    expect($this->calendar->isWorkingTime(CarbonImmutable::parse('2026-09-05 10:00', 'Africa/Cairo')))
        ->toBeFalse();
});
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --filter=WorkingCalendarTest`
Expected: FAIL — `Class "App\Services\Time\WorkingCalendar" not found`.

- [ ] **Step 3: Write the migration, enum and model**

```php
Schema::create('holidays', function (Blueprint $table): void {
    $table->id();
    $table->date('date')->unique();
    $table->json('name');
    $table->enum('type', ['public', 'company'])->default('public');
    $table->boolean('is_recurring')->default(false);
    $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
});
```

`app/Enums/HolidayType.php` is a backed string enum with cases `Public` = `'public'` and `Company` = `'company'`.

`app/Models/Holiday.php` uses `HasTranslations` with `public array $translatable = ['name'];` and casts `date` to `immutable_date`, `is_recurring` to `boolean`.

- [ ] **Step 4: Write the calendar**

`app/Services/Time/WorkingCalendar.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Time;

use App\Models\Holiday;
use App\Models\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class WorkingCalendar
{
    private const MAX_DAYS_SCANNED = 400;

    private ?Collection $holidayKeys = null;

    public function isWorkingTime(CarbonImmutable $at): bool
    {
        return $this->isWorkingDay($at) && $this->isInsideShift($at);
    }

    public function addWorkingMinutes(CarbonImmutable $from, int $minutes): CarbonImmutable
    {
        $cursor = $this->advanceToWorkingTime($from);
        $remaining = $minutes;
        $days = 0;

        while ($remaining > 0) {
            if ($days++ > self::MAX_DAYS_SCANNED) {
                throw new \RuntimeException('Working calendar could not resolve a deadline; check the shift settings.');
            }

            $endOfShift = $this->shiftEnd($cursor);
            $availableToday = $cursor->diffInMinutes($endOfShift);

            if ($remaining <= $availableToday) {
                return $cursor->addMinutes($remaining);
            }

            $remaining -= $availableToday;
            $cursor = $this->advanceToWorkingTime($endOfShift->addMinute());
        }

        return $cursor;
    }

    public function workingMinutesBetween(CarbonImmutable $a, CarbonImmutable $b): int
    {
        if ($a->greaterThan($b)) {
            [$a, $b] = [$b, $a];
        }

        $cursor = $this->advanceToWorkingTime($a);
        $total = 0;

        while ($cursor->lessThan($b)) {
            $endOfShift = $this->shiftEnd($cursor);
            $segmentEnd = $endOfShift->lessThan($b) ? $endOfShift : $b;

            $total += (int) $cursor->diffInMinutes($segmentEnd);

            $cursor = $this->advanceToWorkingTime($endOfShift->addMinute());
        }

        return $total;
    }

    private function advanceToWorkingTime(CarbonImmutable $at): CarbonImmutable
    {
        $cursor = $at;
        $days = 0;

        while (true) {
            if ($days++ > self::MAX_DAYS_SCANNED) {
                throw new \RuntimeException('Working calendar found no working day; check the weekend and holiday settings.');
            }

            if (! $this->isWorkingDay($cursor)) {
                $cursor = $this->startOfShiftOn($cursor->addDay());

                continue;
            }

            if ($cursor->lessThan($this->shiftStart($cursor))) {
                return $this->shiftStart($cursor);
            }

            if ($cursor->greaterThanOrEqualTo($this->shiftEnd($cursor))) {
                $cursor = $this->startOfShiftOn($cursor->addDay());

                continue;
            }

            return $cursor;
        }
    }

    private function isWorkingDay(CarbonImmutable $at): bool
    {
        $weekend = array_map('strtolower', (array) Setting::get('weekend_days', ['friday']));

        if (in_array(strtolower($at->englishDayOfWeek), $weekend, true)) {
            return false;
        }

        return ! $this->holidayKeys()->contains($at->format('Y-m-d'))
            && ! $this->holidayKeys()->contains('*-'.$at->format('m-d'));
    }

    private function isInsideShift(CarbonImmutable $at): bool
    {
        return $at->greaterThanOrEqualTo($this->shiftStart($at))
            && $at->lessThan($this->shiftEnd($at));
    }

    private function shiftStart(CarbonImmutable $on): CarbonImmutable
    {
        return $this->applyTime($on, (string) Setting::get('work_start', '09:00'));
    }

    private function shiftEnd(CarbonImmutable $on): CarbonImmutable
    {
        return $this->applyTime($on, (string) Setting::get('work_end', '17:00'));
    }

    private function startOfShiftOn(CarbonImmutable $day): CarbonImmutable
    {
        return $this->shiftStart($day);
    }

    private function applyTime(CarbonImmutable $on, string $time): CarbonImmutable
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return $on->setTime($hour, $minute, 0);
    }

    private function holidayKeys(): Collection
    {
        return $this->holidayKeys ??= Holiday::all()->flatMap(
            fn (Holiday $holiday): array => $holiday->is_recurring
                ? ['*-'.$holiday->date->format('m-d')]
                : [$holiday->date->format('Y-m-d')]
        );
    }
}
```

Two details that matter and are easy to get wrong:

- The class caches holidays per instance. It must be resolved fresh per request, never registered as a singleton, or an admin adding a holiday will not affect the current process.
- `MAX_DAYS_SCANNED` exists because a misconfigured `weekend_days` containing all seven days would otherwise loop forever. Failing loudly beats hanging.

- [ ] **Step 5: Run the tests to verify they pass**

Run: `php artisan test --filter=WorkingCalendarTest`
Expected: 9 passing tests. If the Thursday case returns `2026-09-05 11:00` when a holiday is present, the holiday lookup is not being consulted — check `holidayKeys()` is not cached across the two tests by a container singleton.

- [ ] **Step 6: Commit**

```bash
git add colortek-api
git commit -m "feat: working calendar with Friday weekend and admin-managed holidays"
```

---

### Task 4: Task statuses, the tasks table, and the state machine

**Spec:** `specs/06-task-and-time-tracking.md` §1, §1.1, §1.2; `specs/03-data-model.md` §6

Tasks are built and proven correct **before** the workflow engine exists. In this task they are created directly by factories.

**Files:**
- Create: `app/Enums/TaskStatus.php`, `app/Enums/TaskPriority.php`
- Create: `database/migrations/*_create_blocker_categories_table.php`
- Create: `database/migrations/*_create_tasks_table.php`
- Create: `database/migrations/*_create_task_support_tables.php` (`task_field_values`, `task_status_events`, `task_dependencies`)
- Create: `app/Models/Task.php`, `app/Models/BlockerCategory.php`, `app/Models/TaskStatusEvent.php`
- Create: `app/Exceptions/InvalidTaskTransition.php`
- Create: `database/factories/TaskFactory.php`
- Test: `tests/Unit/TaskStatusTest.php`

**Interfaces:**
- Consumes: `Department` from Task 2
- Produces: `TaskStatus` enum with `canTransitionTo(TaskStatus $to): bool` and `isClaimable(): bool`; `Task` model; `TaskFactory` with states `ready()`, `claimed()`, `inProgress()`, `blocked()`, `overdue()`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/TaskStatusTest.php`:

```php
<?php

declare(strict_types=1);

use App\Enums\TaskStatus;

it('allows ready to become claimed', function (): void {
    expect(TaskStatus::Ready->canTransitionTo(TaskStatus::Claimed))->toBeTrue();
});

it('refuses ready to jump straight to completed', function (): void {
    expect(TaskStatus::Ready->canTransitionTo(TaskStatus::Completed))->toBeFalse();
});

it('refuses any transition out of completed', function (): void {
    foreach (TaskStatus::cases() as $target) {
        expect(TaskStatus::Completed->canTransitionTo($target))->toBeFalse();
    }
});

it('treats only ready as claimable', function (): void {
    $claimable = array_filter(TaskStatus::cases(), fn (TaskStatus $s): bool => $s->isClaimable());

    expect($claimable)->toBe([TaskStatus::Ready]);
});

it('allows a blocked task to return to the queue', function (): void {
    expect(TaskStatus::Blocked->canTransitionTo(TaskStatus::Ready))->toBeTrue();
});
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --filter=TaskStatusTest`
Expected: FAIL — `Class "App\Enums\TaskStatus" not found`.

- [ ] **Step 3: Write the enum**

`app/Enums/TaskStatus.php`. The allowed transitions are copied exactly from `specs/06-task-and-time-tracking.md` §1.1 — do not add any.

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum TaskStatus: string
{
    case Pending = 'pending';
    case Waiting = 'waiting';
    case Ready = 'ready';
    case Claimed = 'claimed';
    case InProgress = 'in_progress';
    case Paused = 'paused';
    case Blocked = 'blocked';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /** @return array<int, self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Ready, self::Cancelled],
            self::Waiting => [self::Ready, self::Cancelled],
            self::Ready => [self::Claimed, self::Pending, self::Cancelled],
            self::Claimed => [self::InProgress, self::Ready, self::Blocked, self::Cancelled],
            self::InProgress => [self::Paused, self::Blocked, self::Completed, self::Cancelled],
            self::Paused => [self::InProgress, self::Blocked, self::Cancelled],
            self::Blocked => [self::InProgress, self::Paused, self::Ready, self::Cancelled],
            self::Completed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    public function isClaimable(): bool
    {
        return $this === self::Ready;
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Completed, self::Cancelled], true);
    }
}
```

`app/Enums/TaskPriority.php` is a backed string enum: `Low`, `Normal`, `High`, `Urgent`.

- [ ] **Step 4: Write the migrations, models and factory**

`blocker_categories` — seeded with the four confirmed categories from `specs/01` A16:

```php
Schema::create('blocker_categories', function (Blueprint $table): void {
    $table->id();
    $table->string('code', 40)->unique();
    $table->json('name');
    $table->boolean('requires_expected_date')->default(false);
    $table->foreignId('notifies_department_id')->nullable()->constrained('departments')->nullOnDelete();
    $table->boolean('active')->default(true);
    $table->timestamps();
    $table->softDeletes();
});
```

Add to `ReferenceSeeder`: `site_not_ready` (notifies `site`), `missing_material` (notifies `workshop`), `waiting_client` (`requires_expected_date` true), `technical_problem`.

`tasks` — the full column list is in `specs/03-data-model.md` §6. Columns referencing tables that do not exist yet (`instance_id`, `task_definition_id`, `project_id`, `subject_type`/`subject_id`) are created **nullable and without a foreign key** in this migration; Task 6 adds the workflow foreign keys. Include every index named in that section:

```php
$table->index(['department_id', 'status']);
$table->index(['project_id', 'status']);
$table->index(['claimed_by_user_id', 'status']);
$table->index(['status', 'due_at']);
```

`app/Models/Task.php` casts `status` to `TaskStatus::class`, `priority` to `TaskPriority::class`, and the timestamps to `immutable_datetime`. It exposes `department()`, `claimant()`, `statusEvents()`, `fieldValues()`.

`app/Exceptions/InvalidTaskTransition.php`:

```php
<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\TaskStatus;
use RuntimeException;

final class InvalidTaskTransition extends RuntimeException
{
    public static function between(TaskStatus $from, TaskStatus $to): self
    {
        return new self(__('A task cannot move from :from to :to.', [
            'from' => $from->value,
            'to' => $to->value,
        ]));
    }
}
```

`database/factories/TaskFactory.php` with the states named in the Interfaces block above, plus `siteHeld()` which sets status `Pending`.

- [ ] **Step 5: Run the tests to verify they pass**

Run: `php artisan test --filter=TaskStatusTest`
Expected: 5 passing tests.

- [ ] **Step 6: Commit**

```bash
git add colortek-api
git commit -m "feat: task status enum, tasks table and blocker categories"
```

---

### Task 5: TaskService — claim, release, start, pause, block, complete

**Spec:** `specs/06-task-and-time-tracking.md` §2, §3, §4; `specs/11-audit-and-exceptions.md` §5

**Files:**
- Create: `app/Repositories/BaseRepository.php`, `app/Repositories/TaskRepository.php`
- Create: `app/Services/Tasks/TaskService.php`
- Create: `app/Services/Tasks/TaskValidator.php`
- Create: `app/Exceptions/TaskAlreadyClaimed.php`, `app/Exceptions/TaskNotReadyToComplete.php`
- Create: `app/Events/TaskClaimed.php`, `app/Events/TaskCompleted.php`, `app/Events/TaskBlocked.php`
- Test: `tests/Feature/TaskLifecycleTest.php`, `tests/Feature/TaskClaimRaceTest.php`

**Interfaces:**
- Consumes: `Task`, `TaskStatus` from Task 4
- Produces:
  - `TaskService::claim(Task $task, User $user): Task`
  - `TaskService::release(Task $task, User $user): Task`
  - `TaskService::start(Task $task, User $user): Task`
  - `TaskService::pause(Task $task, User $user): Task`
  - `TaskService::block(Task $task, User $user, BlockerCategory $category, string $reason, ?CarbonImmutable $expectedResolution): Task`
  - `TaskService::complete(Task $task, User $user, array $fields, array $attachmentIds): Task` — returns the task; the successors it created are readable via `$task->refresh()->instance->tasks`

  In Task 6 `complete()` gains its engine call. Its signature does not change.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/TaskLifecycleTest.php` covering, at minimum:

```php
<?php

declare(strict_types=1);

use App\Enums\TaskStatus;
use App\Exceptions\InvalidTaskTransition;
use App\Models\Task;
use App\Models\User;
use App\Services\Tasks\TaskService;

it('moves a ready task to claimed and records who took it', function (): void {
    $task = Task::factory()->ready()->create();
    $user = User::factory()->create();

    $claimed = app(TaskService::class)->claim($task, $user);

    expect($claimed->status)->toBe(TaskStatus::Claimed)
        ->and($claimed->claimed_by_user_id)->toBe($user->id)
        ->and($claimed->claimed_at)->not->toBeNull();
});

it('writes a status event for every change', function (): void {
    $task = Task::factory()->ready()->create();
    $user = User::factory()->create();

    app(TaskService::class)->claim($task, $user);

    expect($task->statusEvents()->count())->toBe(1)
        ->and($task->statusEvents()->first()->to_status)->toBe('claimed');
});

it('refuses to start a task the user has not claimed', function (): void {
    $task = Task::factory()->claimed()->create();
    $other = User::factory()->create();

    expect(fn () => app(TaskService::class)->start($task, $other))
        ->toThrow(InvalidTaskTransition::class);
});

it('returns a released task to its department queue and clears the claim', function (): void {
    $task = Task::factory()->claimed()->create();

    $released = app(TaskService::class)->release($task, $task->claimant);

    expect($released->status)->toBe(TaskStatus::Ready)
        ->and($released->claimed_by_user_id)->toBeNull();
});

it('requires a category and a reason to block', function (): void {
    $task = Task::factory()->inProgress()->create();

    expect(fn () => app(TaskService::class)->block($task, $task->claimant, $category, '', null))
        ->toThrow(InvalidArgumentException::class);
});
```

Create `tests/Feature/TaskClaimRaceTest.php`:

```php
<?php

declare(strict_types=1);

use App\Exceptions\TaskAlreadyClaimed;
use App\Models\Task;
use App\Models\User;
use App\Services\Tasks\TaskService;

it('lets only one user win a contested claim', function (): void {
    $task = Task::factory()->ready()->create();
    $first = User::factory()->create();
    $second = User::factory()->create();

    app(TaskService::class)->claim($task, $first);

    expect(fn () => app(TaskService::class)->claim($task->fresh(), $second))
        ->toThrow(TaskAlreadyClaimed::class);
});
```

- [ ] **Step 2: Run them and watch them fail**

Run: `php artisan test --filter="TaskLifecycleTest|TaskClaimRaceTest"`
Expected: FAIL — `Class "App\Services\Tasks\TaskService" not found`.

- [ ] **Step 3: Write the repository and the service**

`TaskRepository::claimAtomically(int $taskId, int $userId): bool` is the important method. It must be a single conditional update, not a read-then-write:

```php
public function claimAtomically(int $taskId, int $userId): bool
{
    return DB::table('tasks')
        ->where('id', $taskId)
        ->where('status', TaskStatus::Ready->value)
        ->whereNull('claimed_by_user_id')
        ->update([
            'status' => TaskStatus::Claimed->value,
            'claimed_by_user_id' => $userId,
            'claimed_at' => now(),
            'updated_at' => now(),
        ]) === 1;
}
```

A read-then-write here hands the same task to two people, which is the failure the whole queue model is built to avoid.

`TaskService` rules:

- Every method calls `transitionTo()`, which checks `TaskStatus::canTransitionTo()` and throws `InvalidTaskTransition` otherwise. The state machine is enforced in the service, never only in the UI.
- Every transition writes a `task_status_events` row inside the same transaction.
- `claim()` calls `claimAtomically()`; a `false` return throws `TaskAlreadyClaimed`, whose message names the current claimant.
- `start()`, `pause()`, `block()` and `complete()` require `$task->claimed_by_user_id === $user->id`, otherwise `InvalidTaskTransition`.
- `block()` requires a non-empty reason and a category, and an expected resolution date when the category has `requires_expected_date`. It accumulates `blocked_seconds` separately from working time so that "this took 3 days" and "we worked on it for 4 hours" are both recorded.
- `complete()` runs `TaskValidator` first, then sets the status, then dispatches `TaskCompleted`.

`TaskValidator::assertReadyToComplete(Task $task, array $fields, array $attachmentIds): void` checks that every key in the definition's `required_fields` has a non-empty value and that every type in `required_attachment_types` is present. It throws `TaskNotReadyToComplete` carrying the **specific** missing field or attachment name — `specs/08-api-contract.md` §1 forbids a generic "validation failed".

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --filter="TaskLifecycleTest|TaskClaimRaceTest"`
Expected: all passing.

- [ ] **Step 5: Commit**

```bash
git add colortek-api
git commit -m "feat: task lifecycle service with atomic claim"
```

---

### Task 6: The workflow engine

**Spec:** `specs/05-workflow-engine.md` — all of it. This is the heart of the product; read the whole file before starting.

**Files:**
- Create: `database/migrations/*_create_workflow_tables.php`
- Create: `app/Models/WorkflowTemplate.php`, `WorkflowTaskDefinition.php`, `WorkflowTransition.php`, `WorkflowInstance.php`, `WorkflowTransitionLog.php`
- Create: `app/Services/Workflow/WorkflowEngine.php`
- Create: `app/Services/Workflow/ConditionEvaluator.php`
- Create: `app/Services/Workflow/TaskFactory.php`
- Create: `app/Services/Tasks/DeadlineCalculator.php`
- Modify: `app/Services/Tasks/TaskService.php` — `complete()` calls the engine
- Create: `database/factories/WorkflowTemplateFactory.php`
- Test: `tests/Unit/ConditionEvaluatorTest.php`, `tests/Feature/WorkflowEngineTest.php`

**Interfaces:**
- Consumes: `TaskService::complete()` from Task 5, `WorkingCalendar` from Task 3
- Produces:
  - `WorkflowEngine::start(WorkflowTemplate $template, Model $subject): WorkflowInstance`
  - `WorkflowEngine::advance(Task $completedTask): Collection<Task>` — the successors created
  - `DeadlineCalculator::for(WorkflowTaskDefinition $definition, ?Project $project, CarbonImmutable $from): ?CarbonImmutable`
  - `ConditionEvaluator::passes(?array $condition, Task $task): bool`

- [ ] **Step 1: Write the failing tests**

`tests/Unit/ConditionEvaluatorTest.php` covers the operators and combinators in `specs/05-workflow-engine.md` §4, and — importantly — that an unresolvable field is treated as empty and logged rather than throwing:

```php
it('treats an unresolvable field as empty instead of throwing', function (): void {
    $task = Task::factory()->create();

    $result = app(ConditionEvaluator::class)->passes(
        ['field' => 'nonexistent', 'operator' => 'is_empty', 'value' => null],
        $task,
    );

    expect($result)->toBeTrue();
});
```

A workflow must not stop because somebody renamed a field.

`tests/Feature/WorkflowEngineTest.php` covers the scenario list in `specs/05-workflow-engine.md` §13. Write all fourteen. The four that matter most:

```php
it('creates exactly one successor when Complete is pressed twice', function (): void {
    [$instance, $first] = seedTwoStepWorkflow();

    app(TaskService::class)->complete($first, $first->claimant, [], []);
    try {
        app(TaskService::class)->complete($first->fresh(), $first->claimant, [], []);
    } catch (InvalidTaskTransition) {
        // expected — the task is already completed
    }

    expect($instance->tasks()->where('task_definition_id', $secondDefinition->id)->count())->toBe(1);
});

it('holds a join_mode=all target until every predecessor completes', function (): void {
    // two parallel predecessors, one target
    expect($target->status)->toBe(TaskStatus::Waiting);
});

it('rolls the whole completion back when transition evaluation fails', function (): void {
    // force ConditionEvaluator to throw
    expect($first->fresh()->status)->toBe(TaskStatus::InProgress)
        ->and(Task::count())->toBe(1);
});

it('leaves running instances on their original template version when the template is republished', function (): void {
    expect($instance->fresh()->template->version)->toBe(1)
        ->and(WorkflowTemplate::where('code', 'test')->max('version'))->toBe(2);
});
```

- [ ] **Step 2: Run them and watch them fail**

Run: `php artisan test --filter="ConditionEvaluatorTest|WorkflowEngineTest"`
Expected: FAIL — the engine classes do not exist.

- [ ] **Step 3: Write the migrations**

The five workflow tables from `specs/03-data-model.md` §5. Two constraints are not optional:

```php
// workflow_templates
$table->unique(['code', 'version']);

// tasks — prevents a double-clicked Complete creating two identical tasks.
// specs/05-workflow-engine.md §6
Schema::table('tasks', function (Blueprint $table): void {
    $table->foreignId('instance_id')->nullable()->change();
    $table->unique(['instance_id', 'task_definition_id', 'open_marker'], 'tasks_one_open_per_definition');
});
```

MySQL has no partial indexes. Implement the "one open task per definition" rule with a generated column:

```php
$table->string('open_marker', 20)->storedAs(
    "CASE WHEN status IN ('completed','cancelled') THEN CONCAT('closed-', id) ELSE 'open' END"
);
```

Completed and cancelled rows get a unique marker so they never collide; open rows all share `'open'`, so a second open task for the same definition violates the unique index. This enforces the rule in the database, not only in application code — which is what makes a double-clicked button safe.

- [ ] **Step 4: Write the engine**

`WorkflowEngine::advance(Task $completedTask)` follows steps 9–14 of `specs/05-workflow-engine.md` §3 exactly:

1. Find transitions whose `from_task_definition_id` matches the completed task's definition.
2. Evaluate each condition through `ConditionEvaluator`.
3. Write a `workflow_transition_log` row for **every** evaluation, taken or not, with the reason. This is what makes a silent failure visible later — `specs/02-architecture.md` §9.
4. For each taken transition, check the target's `join_mode`. For `all`, create the target in status `Waiting` if any predecessor is still open; promote it to `Ready` only when the last one completes. For `any`, create it once.
5. Build each task through `TaskFactory`, which copies `title` and `instructions` from the definition (a snapshot — later template edits must not rewrite history) and reads through to the subject for data. `specs/05-workflow-engine.md` §5.
6. Compute `due_at` through `DeadlineCalculator`.
7. Set the starting status per `specs/05-workflow-engine.md` §8.
8. If no transitions were taken and no tasks remain open, mark the instance `completed`.

**The engine must not be called outside a transaction.** `TaskService::complete()` opens one `DB::transaction()` that wraps validation, the status change and the engine call. A crash halfway must never leave a completed task with no successor — `specs/05-workflow-engine.md` §1 rule 3.

Events are dispatched **after** the transaction commits, using `DB::afterCommit()`. A failed notification must never roll back real work.

- [ ] **Step 5: Run the tests to verify they pass**

Run: `php artisan test --filter="ConditionEvaluatorTest|WorkflowEngineTest"`
Expected: all fourteen scenarios passing.

- [ ] **Step 6: Run the whole suite and the QA gate**

Run: `composer qa`
Expected: clean.

- [ ] **Step 7: Commit**

```bash
git add colortek-api
git commit -m "feat: workflow engine with versioned templates and transactional handover"
```

---

### Task 7: Auth, task endpoints and the enum catalog

**Spec:** `specs/08-api-contract.md` §2, §3, §11b; `specs/04-permissions-and-roles.md` §4

**Files:**
- Create: `app/Http/Controllers/Api/V1/AuthController.php`, `TaskController.php`, `EnumController.php`
- Create: `app/Http/Requests/TaskCompleteRequest.php`, `TaskBlockRequest.php`
- Create: `app/Http/Resources/TaskResource.php`, `TaskListResource.php`, `UserResource.php`
- Create: `app/Http/Filters/TaskFilter.php`
- Create: `app/Policies/TaskPolicy.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/AuthTest.php`, `tests/Feature/TaskEndpointTest.php`

**Interfaces:**
- Consumes: `TaskService` from Task 5
- Produces: the HTTP surface every screen in Plan 2 consumes

- [ ] **Step 1: Write the failing test**

`tests/Feature/AuthTest.php`:

```php
it('returns the permission list, not role names, from me', function (): void {
    $user = User::factory()->create();
    $user->assignRole('sales');

    $this->actingAs($user)->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.permissions.0', fn (string $p): bool => str_contains($p, '.'))
        ->assertJsonMissingPath('data.roles.0.permissions');
});
```

`tests/Feature/TaskEndpointTest.php`:

```php
it('returns a paginator with per_page 15 by default', function (): void {
    Task::factory()->ready()->count(20)->create();

    $this->actingAs($this->salesUser)->getJson('/api/v1/tasks?scope=queue')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 15)
        ->assertJsonCount(15, 'data');
});

it('returns 409 when the task was already claimed', function (): void {
    $task = Task::factory()->claimed()->create();

    $this->actingAs($this->otherUser)->postJson("/api/v1/tasks/{$task->id}/claim")
        ->assertStatus(409)
        ->assertJsonPath('code', 'task.already_claimed');
});

it('names the missing attachment instead of a generic error', function (): void {
    $task = $this->taskRequiringPaymentProof();

    $this->actingAs($task->claimant)->postJson("/api/v1/tasks/{$task->id}/complete", ['fields' => []])
        ->assertStatus(422)
        ->assertJsonPath('code', 'task.missing_required_attachment')
        ->assertJsonPath('errors.attachments\\.payment_proof.0', fn (string $m): bool => str_contains($m, 'payment proof'));
});

it('names the created successor in the completion response', function (): void {
    [$instance, $first] = seedTwoStepWorkflow();

    $this->actingAs($first->claimant)->postJson("/api/v1/tasks/{$first->id}/complete", ['fields' => []])
        ->assertOk()
        ->assertJsonPath('meta.created_tasks.0.department', 'Reception');
});

it('hides tasks on projects the user cannot see', function (): void {
    // a user without project.view_all sees only their own involvement
    expect($response->json('meta.total'))->toBe(1);
});
```

The last two matter most. `meta.created_tasks` is what lets the UI say *"Reception now has 'Review payment'"* — `specs/08-api-contract.md` §3. And the visibility filter must be applied **in the query**, not in the browser — `specs/04-permissions-and-roles.md` §4.

- [ ] **Step 2: Run them and watch them fail**

Run: `php artisan test --filter="AuthTest|TaskEndpointTest"`
Expected: FAIL — routes not defined.

- [ ] **Step 3: Write the controllers, requests, resources, filter and policy**

Controllers stay thin — constructor-inject the service, call one method, return a Resource. No `validated()` parsing, no `if` on data.

`TaskFilter` implements every filter in `specs/08-api-contract.md` §3: `scope`, `department_id`, `project_id`, `status[]`, `overdue`, `priority`, `q`, `due_before`, `sort`. The visibility rules from `specs/04` §4 are applied as a query scope that every task query passes through, so no endpoint can forget them.

`EnumController::show(string $name)` returns `[{value, label}]` for each enum named in `specs/15-engineering-standards.md` §A5, localised through `Accept-Language`.

Register `TaskPolicy` and check permissions with `$this->authorize()`. Never check a role name.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --filter="AuthTest|TaskEndpointTest"`
Expected: all passing.

- [ ] **Step 5: Commit**

```bash
git add colortek-api
git commit -m "feat: auth, task endpoints, filters and the enum catalog"
```

---

### Task 8: Activity events, audit log and the end-to-end proof

**Spec:** `specs/10-notifications-and-activity-stream.md` §1; `specs/11-audit-and-exceptions.md` §1–§3; `specs/13-odoo-gateway-and-seed-data.md` §5

**Files:**
- Create: `database/migrations/*_create_activity_events_table.php`, `*_create_audit_logs_table.php`
- Create: `app/Models/ActivityEvent.php`, `app/Models/AuditLog.php`
- Create: `app/Services/Activity/ActivityRecorder.php`
- Create: `app/Listeners/RecordTaskActivity.php`
- Test: `tests/Feature/ActivityEventTest.php`, `tests/Feature/SpineEndToEndTest.php`

**Interfaces:**
- Consumes: the events dispatched in Tasks 5 and 6
- Produces: `ActivityRecorder::record(string $type, Severity $severity, ...): ActivityEvent`

- [ ] **Step 1: Write the failing test**

`tests/Feature/ActivityEventTest.php`:

```php
it('writes both language messages at the moment of the event', function (): void {
    $task = Task::factory()->ready()->create();

    app(TaskService::class)->claim($task, $user);

    $event = ActivityEvent::latest('id')->first();

    expect($event->message_en)->not->toBeEmpty()
        ->and($event->message_ar)->not->toBeEmpty();
});

it('keeps the original message after the actor is renamed', function (): void {
    app(TaskService::class)->claim($task, $user);
    $original = ActivityEvent::latest('id')->first()->message_en;

    $user->update(['name' => 'Someone Else']);

    expect(ActivityEvent::latest('id')->first()->message_en)->toBe($original);
});

it('does not roll back the task when writing the activity row fails', function (): void {
    // force ActivityRecorder to throw
    expect($task->fresh()->status)->toBe(TaskStatus::Claimed);
});
```

That last test is the reason listeners run after commit — `specs/02-architecture.md` §2 rule 4.

`tests/Feature/SpineEndToEndTest.php` — the most valuable test in the plan:

```php
<?php

declare(strict_types=1);

it('carries a three step workflow across three department queues', function (): void {
    // A template: Sales step -> Reception step -> Accounting step.
    $template = WorkflowTemplate::factory()->threeStep()->create();
    $instance = app(WorkflowEngine::class)->start($template, $project);

    // Step 1 lands in the Sales queue, ready, with a deadline inside working hours.
    $first = $instance->tasks()->sole();
    expect($first->department->code)->toBe('sales')
        ->and($first->status)->toBe(TaskStatus::Ready)
        ->and(app(WorkingCalendar::class)->isWorkingTime($first->due_at))->toBeTrue();

    // A salesperson claims, starts and completes it.
    $sales = User::factory()->inDepartment('sales')->create();
    app(TaskService::class)->claim($first, $sales);
    app(TaskService::class)->start($first->fresh(), $sales);
    app(TaskService::class)->complete($first->fresh(), $sales, ['amount' => 50000], []);

    // Reception's task now exists, in Reception's queue, without anyone forwarding anything.
    $second = $instance->tasks()->where('id', '!=', $first->id)->sole();
    expect($second->department->code)->toBe('reception')
        ->and($second->status)->toBe(TaskStatus::Ready);

    // And the same again into Accounting.
    $reception = User::factory()->inDepartment('reception')->create();
    app(TaskService::class)->claim($second, $reception);
    app(TaskService::class)->start($second->fresh(), $reception);
    app(TaskService::class)->complete($second->fresh(), $reception, [], []);

    expect($instance->tasks()->where('status', TaskStatus::Ready)->sole()->department->code)
        ->toBe('accounting');

    // The whole thing is visible in the feed.
    expect(ActivityEvent::where('type', 'task.created')->count())->toBe(3);
});
```

If this test passes, the product's central claim is true: finishing a task hands the work to the next department automatically, with no human forwarding anything.

- [ ] **Step 2: Run them and watch them fail**

Run: `php artisan test --filter="ActivityEventTest|SpineEndToEndTest"`
Expected: FAIL.

- [ ] **Step 3: Write the tables, recorder and listener**

`activity_events` and `audit_logs` exactly as in `specs/03-data-model.md` §10. Note `activity_events` keeps `message_en` and `message_ar` as **separate columns**, not a translatable JSON column — `specs/15-engineering-standards.md` §A4 explains why.

`ActivityRecorder` renders both messages at write time. `RecordTaskActivity` listens for `TaskCreated`, `TaskClaimed`, `TaskCompleted` and `TaskBlocked`, and is registered to run after commit.

Audit rows are written **inside** the same transaction as the change they describe — `specs/11-audit-and-exceptions.md` §3 rule 1. This is the opposite of the activity rule, and deliberately so: losing a feed line is annoying, losing an audit row is a problem.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --filter="ActivityEventTest|SpineEndToEndTest"`
Expected: all passing.

- [ ] **Step 5: Run the full gate**

Run: `composer qa`
Expected: clean.

- [ ] **Step 6: Commit**

```bash
git add colortek-api
git commit -m "feat: activity stream, audit log and the end-to-end spine test"
```

---

## Definition of done for this plan

- [ ] `composer qa` passes clean.
- [ ] `SpineEndToEndTest` passes — a completed task creates its successor in the correct department queue, three times in a row, with no human forwarding.
- [ ] All fourteen scenarios in `specs/05-workflow-engine.md` §13 pass.
- [ ] All nine working-calendar cases pass, including the Thursday-15:00-over-a-Friday-and-a-holiday case.
- [ ] A double-clicked completion creates exactly one successor, enforced by a database constraint.
- [ ] A contested claim is won by exactly one user and the loser gets a 409 naming who took it.
- [ ] No task can be created anywhere except through the workflow engine or an explicit ad-hoc factory in tests.

When these hold, Plan 2 can begin: the payment workflow and the first screens.
