# Colortek Phase 1 — Admin Configuration — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Super admins and operational admins can configure the company calendar, manage roles/users/employees, read and edit workflow templates (draft → publish), maintain the site condition checklist, and inspect the three operational failure lists — all through admin SPA pages backed by `/api/v1/admin/*` CRUD.

**Depends on:** Plans 1–2 (spine + payment flow). Plans 3–5 may still be in progress; this plan adds admin APIs and UI without blocking on sample/site/live features, but demo coverage improves once those workflows exist.

**Architecture:** Extend the Laravel API with admin controllers, policies, services, and audit writes. Reuse existing `WorkingCalendar`, `Holiday`, `Setting`, `WorkflowTemplate`, Spatie roles/permissions, and `ReferenceSeeder` patterns. Next.js admin routes under `(with-layouts)/admin/*`, permission-gated from `/auth/me` permissions (never role names).

**Spec:** `specs/09-screens/06-admin-calendar-and-holidays.md`, `specs/09-screens/07-admin-roles-and-permissions.md`, `specs/04-permissions-and-roles.md`, `specs/05-workflow-engine.md` §2 & §12, `specs/03-data-model.md` §2–3 & §9, `specs/08-api-contract.md` §11 & §11b, `specs/11-audit-and-exceptions.md` §2 & §6, `specs/09-screens/00-screen-map.md` §18–20, `specs/16-sla-defaults.md`, `specs/15-engineering-standards.md`

**Working directory:** `/workspace/colortek-api` (API), `/workspace/colortek-frontend` (UI)

---

## What already exists (do not rebuild)

| Area | State |
|---|---|
| `WorkingCalendar`, `DeadlineCalculator` | Implemented; reads `settings` + `holidays`, supports recurring holidays (`*-m-d` keys) |
| `Holiday` model + migration | Table ready; no admin CRUD yet |
| `Setting` model + seeded keys | `work_start`, `work_end`, `weekend_days`, thresholds in `ReferenceSeeder` |
| Roles & permissions | Full Phase 1 permission list + matrix in `ReferenceSeeder`; `super_admin` syncs all |
| Blocker categories | Four categories seeded in `ReferenceSeeder`; exposed via `GET /enums/blocker_category` |
| `WorkflowTemplate` + engine versioning | Factory tests prove running instances stay pinned; only `payment_cycle` v1 seeded |
| Admin routes | **None** — `routes/api.php` stops at payments/journals/tasks |

**Gap to close:** `tasks` has no `due_at_overridden` flag (required by calendar recalc spec §3.5). `site_checklist_items` table/model/seeder do not exist yet.

---

## The three failure lists

From `specs/11-audit-and-exceptions.md` §6 and `specs/09-screens/00-screen-map.md` §20. These are **admin diagnostic lists**, not email alerts:

| # | Name | What it catches | Query sketch |
|---|---|---|---|
| 1 | **Stalled instances** | `workflow_instances.status = running` but zero open tasks (`ready`, `claimed`, `in_progress`, `blocked`, `waiting`, `pending`) — e.g. cancelled last path | `GET /admin/stalled-instances` |
| 2 | **Unclaimed queues** | Tasks in `ready`, unclaimed, `due_at` passed — department queue nobody is watching | `GET /admin/unclaimed-tasks` *(add — implied by architecture §9, not yet in §11 table)* |
| 3 | **Failed jobs** | Rows in `failed_jobs` with exception + retry | `GET /admin/failed-jobs`, `POST /admin/failed-jobs/{uuid}/retry` |

Each list is paginated (default 15). Permission: `settings.manage` (same gate as other operational admin screens in §20). Include **permission coverage warnings** on stalled/unclaimed views when a workflow step has no active permission holder (e.g. nobody with `sample.approve_manager`). `specs/09-screens/07-admin-roles-and-permissions.md` §5.

---

## Phase 1 workflow-template edit scope

Full graph editing (add/remove transitions, rewire branches) is **out of scope**. Phase 1 admin may:

- **Read** every template version: code, scope, version, active/draft, task definitions (code, department, SLA, form flags), transitions (read-only).
- **Edit a draft** cloned from the active published version: translatable `title` / `instructions`, `sla_minutes`, `escalate_after_minutes`, `priority` on task definitions; company settings keys in `settings`.
- **Publish** draft → sets `published_at`, `is_active = true`, deactivates prior active version for same `code`. Running instances unchanged. `specs/05-workflow-engine.md` §2.

Creating a draft from published is `POST /admin/workflow-templates/{id}/publish`'s inverse: first PATCH on a published template auto-creates version N+1 draft (or explicit `POST` clone — pick one pattern and test it).

---

## Task 1: Schema additions — checklist items and deadline override

**Files:**
- Migration: `create_site_checklist_items_table` per `specs/03-data-model.md` §9 (`code` unique, translatable `label`, `answer_type`, `unit`, `is_readiness_critical`, `allows_note`, `sort_order`, `active`, soft deletes)
- Migration: add `due_at_overridden` boolean default `false` to `tasks`
- Model: `SiteChecklistItem` with `HasTranslations`, factory
- Seeder: `SiteChecklistSeeder.php` — five items from `specs/03-data-model.md` §9 seed table (exact Arabic labels); call from `ReferenceSeeder`
- Extend `ReferenceSeederTest` / new `SiteChecklistSeederTest`

**Check:** `php artisan migrate:fresh --seed` → five checklist rows; `SiteChecklistItem::where('code','humidity')->exists()`

---

## Task 2: Permission catalog and admin policies

**Files:**
- `config/permissions.php` (or `app/Support/PermissionCatalog.php`) — every permission from `specs/04-permissions-and-roles.md` §2 grouped by area (Projects, Tasks, …) with English plain-language descriptions for the picker UI
- Mark **dangerous** permissions list: `site.override_block`, `payment.skip_proof`, `journal.reopen`, `formula.update_registered`, `time.correct`, `task.override_deadline`, `audit.view`
- Policies: `SettingPolicy`, `HolidayPolicy`, `RolePolicy`, `UserPolicy`, `EmployeePolicy`, `WorkflowTemplatePolicy`, `SiteChecklistItemPolicy`, `AdminDiagnosticsPolicy` — all authorize on permission names, never role names
- Register policies in `AppServiceProvider`
- Helper: `User::isSuperAdmin(): bool` — has `super_admin` role; used only for UI hints and server-side guards on destructive role operations, **not** for feature authorization in controllers

**Check:** Unit test: `Gate::forUser($admin)->allows('update', Setting::class)` true; sales user false

---

## Task 3: Settings and holidays API

**Spec:** `specs/09-screens/06-admin-calendar-and-holidays.md` §API, `specs/08-api-contract.md` §11

**Files:**
- `SettingRepository`, `SettingService`, `HolidayRepository`, `HolidayService`
- `AdminSettingController`: `GET /admin/settings`, `PATCH /admin/settings`
- `AdminHolidayController`: `GET /admin/holidays`, `POST`, `PATCH /admin/holidays/{id}`, `DELETE`
- Form Requests: `UpdateSettingsRequest` (validate `work_start`/`work_end` HH:MM, end after start, `weekend_days` array of valid day names; also allow `humidity_max`, `sample_repeat_attempt_threshold`, `block_all_when_site_not_ready`, `default_locale`)
- Resources: `SettingResource`, `HolidayResource` (include `created_by` relation when loaded)
- Routes under `auth:sanctum` + permission middleware

**Rules:**
- `GET /admin/settings` requires `settings.manage`; holiday endpoints require `holiday.manage` (admin role has both per matrix)
- PATCH settings writes `audit_logs` row (`event = updated`, old/new values) in same transaction — `specs/11-audit-and-exceptions.md` §2
- Holiday CRUD writes audit rows; set `created_by_user_id` on create
- 404 for unauthorized users (never 403 leak on admin routes per screen specs)

**Check:** HTTP test: admin patches `work_end` → 200 + audit row; sales user → 404

---

## Task 4: Calendar impact preview and deadline recalculation

**Spec:** `specs/09-screens/06-admin-calendar-and-holidays.md` §3–§API

**Files:**
- `CalendarImpactService` — dry-run: given proposed settings delta and/or holiday change, count open tasks whose `due_at` would change (status not `completed`/`cancelled`, `due_at_overridden = false`, has `ready_at` + definition SLA)
- `AdminCalendarController`: `POST /admin/calendar/impact` — body mirrors settings/holiday patch payload; returns `{ affected_task_count }`
- `RecalculateDeadlines` job — for each affected task, recompute `due_at` via `DeadlineCalculator` from `ready_at` (fallback: `created_at`) and definition/project SLA profile
- Wire job dispatch from `HolidayService` and `SettingService` **after** confirm (controller accepts `confirm: true` or separate confirm endpoint — prefer single PATCH with required confirmation flag when count > 0)
- Activity event: *"Calendar changed by {user} — N task deadlines recalculated."*
- When completing a task via existing `PATCH /tasks/{id}/deadline`, set `due_at_overridden = true` (extend `TaskService` if endpoint exists; add endpoint if missing)

**Check:** Feature tests from `specs/09-screens/06-admin-calendar-and-holidays.md` §Tests (all 7 scenarios)

---

## Task 5: Roles, permissions, and coverage API

**Spec:** `specs/09-screens/07-admin-roles-and-permissions.md` §2 & §6

**Files:**
- `AdminRoleController`: `GET/POST/PATCH/DELETE /admin/roles`
- `AdminPermissionController`: `GET /admin/permissions` — grouped catalog with descriptions
- `RoleService` with guards:
  - `super_admin` role: cannot delete, cannot PATCH permissions (always all)
  - Role in use: delete blocked; return user count
  - Last `super_admin`: cannot remove role or deactivate self
- Form Requests: `RoleRequest` (name unique, permission ids subset of seeded catalog)
- Resources: `RoleResource` (permission count, user count), `PermissionGroupResource`
- `AdminAccessController`: `GET /admin/access/coverage` — permissions required by active workflow task definitions / known workflow steps that zero active users hold (start with `sample.approve_manager`; extensible list)
- Audit: every role permission change logs old/new permission name arrays

**Check:** Feature tests §8 scenarios 1–4, 6–7 from roles screen spec

---

## Task 6: Users and employees API

**Spec:** `specs/09-screens/07-admin-roles-and-permissions.md` §3–§4 & §6

**Files:**
- `AdminUserController`: `GET/POST/PATCH /admin/users`, `POST /admin/users/{id}/roles` (sync roles — requires `role.assign`), `GET /admin/users/{id}/effective-permissions`
- `AdminEmployeeController`: `GET/POST/PATCH /admin/employees`
- `UserService`: department pivot sync with `is_supervisor`; deactivate with claimed-task check → optional `release_claimed_tasks` flag releases tasks to department queues
- `EmployeeService`: soft-deactivate only if hours exist (never hard-delete with time entries)
- Form Requests: `UserRequest`, `EmployeeRequest`, `SyncUserRolesRequest`
- Resources: `AdminUserResource`, `EmployeeResource`, `EffectivePermissionResource` (merged unique permissions as plain-language sentences from catalog)
- Filters: `UserFilter`, `EmployeeFilter` (`q`, `active`, `department_id`)

**Check:** Feature tests §8 scenarios 2, 3, 5, 8; `admin` can PATCH user name but `POST .../roles` → 404

---

## Task 7: Workflow template admin API

**Spec:** `specs/05-workflow-engine.md` §2, `specs/08-api-contract.md` §11, `specs/16-sla-defaults.md`

**Files:**
- `WorkflowTemplateRepository`, `WorkflowTemplateAdminService`
- `AdminWorkflowTemplateController`:
  - `GET /admin/workflow-templates` — paginated; filter by `code`, `is_active`, include latest draft per code
  - `GET /admin/workflow-templates/{id}?relations=definitions,transitions` — eager-load departments
  - `PATCH /admin/workflow-templates/{id}` — **draft only**; update template name + nested definition fields (SLA, titles, instructions, priority)
  - `POST /admin/workflow-templates/{id}/draft` — clone published active → version N+1 draft (if no draft exists)
  - `POST /admin/workflow-templates/{id}/publish` — validate draft complete, set `published_at`, flip `is_active`, deactivate siblings
- Form Requests: `UpdateWorkflowTemplateRequest`, `PublishWorkflowTemplateRequest`
- Resources: `WorkflowTemplateResource`, `WorkflowTaskDefinitionResource`, `WorkflowTransitionResource` (transitions read-only in Phase 1)
- Audit on publish (`event = updated`, template id + version)
- Permission: `workflow.view` for GET, `workflow.manage` for mutate

**Check:** Republish test mirrors `WorkflowEngineTest` "running instances stay on original version"; draft cannot be instantiated (`WorkflowEngine::start` throws)

---

## Task 8: Site checklist items admin API

**Spec:** `specs/03-data-model.md` §9, `specs/08-api-contract.md` §7 & §11b

**Files:**
- `SiteChecklistItemRepository`, `SiteChecklistItemService`
- `AdminSiteChecklistItemController`: `GET/POST/PATCH /admin/site-checklist-items` (soft delete via `active = false` or `deleted_at` — match engineering standards soft-delete pattern)
- Public read alias: `GET /site-checklist-items` for site engineers (existing contract §7)
- Options: `GET /options/checklist-items` — id + localized label, active only
- Form Request: `SiteChecklistItemRequest` — protect `code` immutability after create; validate `answer_type` enum
- Resource: `SiteChecklistItemResource`
- Permission: `settings.manage`
- Audit on update (label/critical flag changes affect readiness logic)

**Check:** HTTP test: reorder via `sort_order`; deactivate item excluded from options endpoint

---

## Task 9: Three failure lists API

**Spec:** `specs/11-audit-and-exceptions.md` §6, `specs/02-architecture.md` §9, `specs/08-api-contract.md` §11

**Files:**
- `AdminStalledInstanceController`: `GET /admin/stalled-instances` — instance, template code/version, project reference, last completed task, stalled since, coverage warnings
- `AdminUnclaimedTaskController`: `GET /admin/unclaimed-tasks` — task reference, department, age past due, project; sort by lateness
- `AdminFailedJobController`: `GET /admin/failed-jobs`, `POST /admin/failed-jobs/{uuid}/retry` — dispatch `queue:retry` equivalent via `Artisan::call` or `RetryFailedJob` wrapper
- Services with explicit queries (indexed columns from `tasks` and `workflow_instances`)
- Resources: `StalledInstanceResource`, `UnclaimedTaskResource`, `FailedJobResource`
- Permission: `settings.manage`

**Check:** Feature tests: seeded stalled instance (cancel only open task on running instance) appears in list; ready unclaimed overdue task appears; failed job retry increments attempt

---

## Task 10: Admin API integration tests

**Files:**
- `tests/Feature/Admin/CalendarAdminTest.php` — all 7 calendar tests
- `tests/Feature/Admin/RolesAndUsersAdminTest.php` — all 8 roles screen tests
- `tests/Feature/Admin/WorkflowTemplateAdminTest.php` — draft/publish/version pin
- `tests/Feature/Admin/ChecklistItemsAdminTest.php`
- `tests/Feature/Admin/FailureListsAdminTest.php`
- `tests/Feature/Admin/SettingsAuditTest.php` — audit row in same transaction

**Check:** `php artisan test --filter=Admin`

---

## Task 11: Frontend — admin shell, services, and navigation

**Files:**
- Read `design-system/DESIGN-SYSTEM.md` before any UI work; use tokens only
- `src/services/admin/` — typed clients for settings, holidays, roles, users, employees, workflow templates, checklist items, failure lists
- Extend auth context/hook to expose permission checks (`can('settings.manage')`)
- `src/components/common/sidebar/data.tsx` — Admin section with links gated by permission:
  - `/admin/calendar` — `settings.manage` or `holiday.manage`
  - `/admin/access` — `role.manage` (Roles tab) / `user.manage` (Users) / `employee.manage` (Employees)
  - `/admin/workflows` — `workflow.view`
  - `/admin/checklist` — `settings.manage`
  - `/admin/failures` — `settings.manage`
- Route group: `src/app/(with-layouts)/admin/layout.tsx` — permission guard; 404 page when no admin access
- Reuse existing axios config + React Query patterns from `src/services/taskService.ts`

**Check:** Login as super admin → admin nav visible; login as sales → admin nav hidden, `/admin/calendar` shows not-found

---

## Task 12: Frontend — Calendar and holidays (`/admin/calendar`)

**Spec:** `specs/09-screens/06-admin-calendar-and-holidays.md`

**Sections:**
1. **Working hours** — shift start/end, weekend multi-select, read-only timezone `Africa/Cairo`, helper text *"One working day = 8 hours"*
2. **Warning banner** — plain copy that changing calendar affects every open deadline
3. **Holidays table** — grouped by year, columns date / name (en+ar) / type / recurring / added by; add/edit/delete modals
4. **Recurring helper** — Islamic holidays note beside checkbox
5. **Save flow** — call `POST /admin/calendar/impact` first; confirmation dialog with affected count; then PATCH with confirm
6. **Month preview** — read-only grid shading weekends + holidays; click day scrolls to row
7. **States:** skeleton, empty ("No holidays added yet…"), saving dialog

**Check:** Manual: add holiday → confirm dialog shows count → open task deadline shifts (via API poll)

---

## Task 13: Frontend — Access management (`/admin/access`)

**Spec:** `specs/09-screens/07-admin-roles-and-permissions.md`

**Tabs:**
1. **Roles** — visible only with `role.manage`; list with permission/user counts; create/edit drawer with grouped permission picker + dangerous-permission confirmation; delete blocked with user count
2. **Users** — list; edit drawer with roles multi-select (`role.assign`), departments + supervisor flags, locale, active toggle; **effective permissions** summary panel in plain language
3. **Employees** — non-login worker CRUD; link optional `user_id`
4. **Coverage banner** — from `GET /admin/access/coverage` when e.g. no sample approver
5. **Guards in UI:** hide Roles tab for non–super-admin; lock last super admin's own role checkbox

**Check:** Super admin creates role with `payment.confirm` → assign to user → effective view lists it; operational admin cannot open Roles tab

---

## Task 14: Frontend — Workflow templates (`/admin/workflows`)

**Spec:** `specs/05-workflow-engine.md` §2, `specs/16-sla-defaults.md`

**UI:**
- List: one row per `code`, show active version + draft badge if exists
- Detail: read-only transition diagram or ordered step list; editable fields on draft only (SLA minutes, escalate, titles/instructions en+ar)
- Actions: "Create draft from published", "Publish" (confirm: new instances only)
- Mark SLA values from seed as *proposed — confirm with client* per `16-sla-defaults.md`

**Check:** Edit `reception_review_payment` SLA on draft → publish → new payment instance uses new SLA; running payment tasks unchanged

---

## Task 15: Frontend — Checklist items and failure lists

**Checklist (`/admin/checklist`):**
- Table of five seeded items; edit labels (en/ar), `is_readiness_critical`, `sort_order`, active
- Match paper-form wording note in UI for Arabic labels

**Failures (`/admin/failures`):**
- Three tabs: **Stalled instances**, **Unclaimed queues**, **Failed jobs**
- Stalled/unclaimed: link to project/task; show coverage warnings inline
- Failed jobs: expand exception, Retry button → `POST .../retry`
- Empty states explain what each list means (not generic "no data")

**Check:** Demo seed includes at least one row per list for QA walkthrough (optional `DemoSeeder` addition)

---

## Task 16: Options and enum wiring

**Files:**
- Ensure `GET /options/checklist-items`, `GET /options/departments`, `GET /options/users` work for admin pickers
- Add enum `weekend_day` or reuse validated day list in frontend constants fed by settings GET
- Add `GET /enums/holiday_type` if not present

**Check:** Site visit form (when built) and admin pickers consume options endpoints — no hardcoded checklist labels in frontend

---

## Definition of done

- [ ] `composer qa` passes (API)
- [ ] `php artisan test --filter=Admin` green
- [ ] `npm run lint` passes (frontend)
- [ ] Super admin: calendar change shows impact count and recalculates open tasks; overridden deadlines untouched
- [ ] Super admin: role/user/employee CRUD with audit trail; operational admin blocked from role assignment
- [ ] Admin: workflow draft → publish creates new version; running instances pinned
- [ ] Admin: checklist items editable; five seed items present
- [ ] Admin: three failure lists load; failed job retry works
- [ ] Demo: login as super admin → walk all admin pages without console errors

---

## Out of scope (Phase 1)

- Visual workflow graph editor; adding/removing task definitions or transitions
- Blocker category admin UI (seeded fixed set; enum only)
- Audit log viewer (`audit.view`) — Plan 6 README does not include it; defer to Plan 5 live/admin polish or Phase 2
- Email digests for failure lists — Phase 2 per `specs/11-audit-and-exceptions.md` §6
- Seeding national holidays in production (`specs/13-odoo-gateway-and-seed-data.md` §3.1)
