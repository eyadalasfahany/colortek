# Colortek Spec vs Implementation Audit — Plans 1 & 2

**Branch audited:** `origin/cursor/staging-4e6f` (commit `f3c64aa`)  
**Date:** 2026-08-26  
**Scope:** Plan 1 (Spine) + Plan 2 (Payment Flow vertical slice)  
**Codebases:** `colortek-api/`, `colortek-frontend/`  
**Method:** Read all specs under `specs/`; compare to committed code; run test suite on clean checkout.

---

## Executive summary

The **backend spine and payment workflow are largely implemented and tested**. On a clean checkout, `composer qa` equivalent (`php artisan test`) reports **81/81 tests passing**, including all **16 PaymentFlowTest scenarios** (spec workflow 01 §5 scenarios 1–11 plus HTTP extras).

The largest gaps are on the **frontend**: dynamic forms use the wrong schema field identifier, reception payment context (`subject`) is not rendered, navigation does not follow the permission-based screen map, and several task-detail actions/states from `specs/09-screens/02-task-detail.md` are missing. The **API** implements the Plan 2 payment slice but is missing several task lifecycle endpoints, journal admin routes, options catalogs, idempotency, and attachment deletion declared in `specs/08-api-contract.md`.

---

## Test verification (PaymentFlowTest)

| # | Spec scenario | Test name | Status |
|---|---|---|---|
| 1 | Sales cannot complete without proof | `scenario 1: sales cannot complete without a proof file` | ✅ Pass |
| 2 | Sales cannot complete with `quotation_locked` false | `scenario 2: sales cannot complete with quotation_locked false` | ✅ Pass |
| 3 | Reception task carries payment details | `scenario 3: reception task appears carrying payment details without re-entry` | ✅ Pass |
| 4 | Accepted payment attaches to today's journal | `scenario 4: reception accepted attaches the payment to today's journal` | ✅ Pass |
| 5 | Query creates sales clarify task, no journal | `scenario 5: reception query creates a sales clarify task and skips the journal` | ✅ Pass |
| 6 | Three payments share one journal | `scenario 6: three payments reviewed on the same day share one journal` | ✅ Pass |
| 7 | Submitted journal is read-only | `scenario 7: a submitted journal is read-only` | ✅ Pass |
| 8 | Amount change after submit does not alter journal total | `scenario 8: changing a payment amount after journal submission does not change the journal total` | ✅ Pass |
| 9 | Accounting query reopens journal + audit | `scenario 9: accounting query reopens the journal, creates a reception task, and writes an audit row` | ✅ Pass |
| 10 | Installment 2 is independent instance | `scenario 10: installment 2 runs a second independent instance while installment 1 is still open` | ✅ Pass |
| 11 | Empty day auto-closes journal task | `scenario 11: a day with no payments does not leave a stuck open task` | ✅ Pass |

**File:** `colortek-api/tests/Feature/PaymentFlowTest.php`

Additional passing tests in the same file: template seed check, `OpenDailyJournal` job, HTTP start-payment + `form_schema`, flat `attachment_ids`, attachment streaming.

---

# Plan 1 — The Spine

## ✅ Implemented and matches spec

### Tooling & standards
- Laravel 13 API with `declare(strict_types=1)`, Pint, PHPStan, Pest — `colortek-api/composer.json`, `phpstan.neon`, `pint.json`
- `composer qa` script runs Pint + PHPStan + tests — `colortek-api/composer.json`
- App timezone `Africa/Cairo`, queue `database` — `colortek-api/config/app.php`, `.env.example`

### Reference data & permissions
- 8 departments with translatable names — `database/seeders/ReferenceSeeder.php`, `app/Models/Department.php`
- All **61 permissions** seeded — `ReferenceSeeder::PERMISSIONS`
- 11 roles + matrix matches `specs/04-permissions-and-roles.md` §3 exactly (verified programmatically)
- `payment.skip_proof` granted to **super_admin only** — `tests/Feature/ReferenceSeederTest.php`
- 4 blocker categories — `ReferenceSeeder::seedBlockerCategories()`
- Shift settings `09:00–17:00`, weekend Friday — `ReferenceSeeder`, `tests/Feature/ReferenceSeederTest.php`

### Working calendar
- `WorkingCalendar` with holiday + Friday handling — `app/Services/Time/WorkingCalendar.php`
- 9 unit tests including Thursday-15:00-over-weekend — `tests/Unit/WorkingCalendarTest.php`
- Non-singleton binding — `app/Providers/AppServiceProvider.php` (`bind`, not `singleton`)

### Task lifecycle
- 9-status enum with spec transitions — `app/Enums/TaskStatus.php`, `tests/Unit/TaskStatusTest.php`
- Atomic claim, lifecycle service, status events — `app/Services/Tasks/TaskService.php`, `app/Repositories/TaskRepository.php`
- Claim race → 409 — `tests/Feature/TaskClaimRaceTest.php`, `bootstrap/app.php` exception render
- Specific missing field/attachment errors — `app/Services/Tasks/TaskValidator.php`, `app/Exceptions/TaskNotReadyToComplete.php`

### Workflow engine
- Versioned templates, transitions, join modes, conditions — `app/Services/Workflow/WorkflowEngine.php`, `app/Services/Workflow/ConditionEvaluator.php`
- One-open-task-per-definition DB constraint — `database/migrations/2026_08_25_240006_add_workflow_constraints_to_tasks_table.php`
- 14 engine scenarios — `tests/Feature/WorkflowEngineTest.php` (matches `specs/05-workflow-engine.md` §13)
- End-to-end 3-department handover — `tests/Feature/SpineEndToEndTest.php`

### HTTP surface (Plan 1 scope)
- Auth login/logout/me with **permissions list** (not roles) — `app/Http/Controllers/Api/V1/AuthController.php`, `app/Http/Resources/UserResource.php`, `tests/Feature/AuthTest.php`
- Task list/show/claim/release/start/pause/block/complete — `routes/api.php`, `app/Http/Controllers/Api/V1/TaskController.php`
- Task filters: scope, department, project, status, overdue, priority, search, sort — `app/Http/Filters/TaskFilter.php`
- Row-level visibility in query — `TaskFilter::applyVisibility()` (matches `specs/04-permissions-and-roles.md` §4)
- Paginator default `per_page=15` — `app/Services/Tasks/TaskQueryService.php`
- Enum catalog (partial) — `app/Http/Controllers/Api/V1/EnumController.php`
- Policies use permissions — `app/Policies/TaskPolicy.php`

### Activity & audit foundations
- `activity_events` + `audit_logs` tables — migrations `240007`, `240008`
- ActivityRecorder + bilingual messages at write time — `app/Services/Activity/ActivityRecorder.php`
- Auto-discovered listener — `app/Listeners/RecordTaskActivity.php`
- Activity tests — `tests/Feature/ActivityEventTest.php`

---

## ⚠️ Partial

| Gap | Spec reference | What exists | What's missing |
|---|---|---|---|
| Task API endpoints | `08-api-contract.md` §3 | claim, release, start, pause, block, complete | `resume`, `unblock`, `comment`, `reassign`, `deadline` PATCH, ad-hoc `POST /tasks` |
| Enum catalog | `08-api-contract.md` §11b | `task_status`, `task_priority`, `blocker_category`, payment/journal/project enums | `sample_status`, `formula_status`, `site_readiness`, `corrective_action_status`, `responsible_party`, `approval_type`, `approval_decision`, `attachment_type`, `time_entry_source`, `project_status` |
| Options endpoints | `08-api-contract.md` §11b | — | All `/options/*` routes |
| Idempotency | `08-api-contract.md` §1 | — | No `Idempotency-Key` handling on claim/complete |
| Timer / time tracking | `06-task-and-time-tracking.md`, `08-api-contract.md` §8 | Plan 1 lists `TimerService` in file structure | No `TimeEntry` model, `TimerService`, or timer API routes |
| Activity event types | `10-notifications-and-activity-stream.md` §1 | `task.*` via listener; `payment.confirmed` inline in handler | Many workflow events not emitted (e.g. `payment.queried`, `journal.submitted`, `journal.reopened`) |
| Audit coverage | `11-audit-and-exceptions.md` §2 | Journal reopen audited in payment flow tests | No audit for payment record changes, reassignment, deadline overrides |
| `TaskResource` relations | `08-api-contract.md` §1 | Hard-coded eager loads in controller | No `?relations=` query support |

---

## ❌ Missing or wrong

| Item | Spec | Notes |
|---|---|---|
| `Employee` CRUD / listing API | `08-api-contract.md` §8 `GET /employees` | Model/migration exist; no route |
| Attachment DELETE | `08-api-contract.md` §12 | Not routed |
| Friendly 404 messages everywhere | `15-engineering-standards.md` §A3 | Used in some controllers; not verified all endpoints |
| `TaskListResource` vs `TaskResource` split | Plan 1 file structure | Only `TaskResource` + collection used |

---

## 🔜 Correctly deferred to Phase 2+

| Item | Spec | Reason |
|---|---|---|
| Odoo gateway / `odoo_sync_log` | `13-odoo-gateway-and-seed-data.md`, workflow 01 §4 | Phase 2; Phase 1 records intent only |
| Live SSE `/stream`, notifications API | `08-api-contract.md` §9, `10-notifications-and-activity-stream.md` | Plan 5 (live layer) |
| Dashboards, global search | `08-api-contract.md` §10, screen map §21 | Plan 5+ |
| Admin screens/API | `08-api-contract.md` §11, screens 18–20 | Plan 6 |
| Samples, site, formula workflows | `07-workflows/02–05` | Plans 3–4 |
| Email/WhatsApp notifications | `10-notifications-and-activity-stream.md` §3, `14-phase-2-backlog.md` §7 | Phase 2 backlog |

---

# Plan 2 — Payment Flow Vertical Slice

## ✅ Implemented and matches spec

### Data model
- `clients`, `quotations`, `payments`, `journals`, `journal_payment`, `attachments` — migrations `250001–250006`
- Money as `decimal(14,2)` + `currency` — payment/quotation migrations
- Project ↔ client/quotation relations — `database/migrations/2026_08_25_250007_add_quotation_id_to_projects_table.php`, `app/Models/Project.php`

### Workflow template `payment_cycle`
All **6 task definition codes** match `specs/07-workflows/01-payment-to-accounting.md` §2–§3:

| Code | Seeder | Department | SLA (minutes) |
|---|---|---|---|
| `sales_confirm_payment` | ✅ | sales | 240 |
| `reception_review_payment` | ✅ | reception | 240 |
| `sales_clarify_payment` | ✅ | sales | 240 |
| `reception_daily_journal` | ✅ | reception | null (end-of-day) |
| `accounting_process_journal` | ✅ | accounting | 480 |
| `reception_fix_journal` | ✅ | reception | 240 |

**Files:** `database/seeders/PaymentWorkflowSeeder.php`, called from `ReferenceSeeder.php`

Transitions match spec §3 including conditional `review_result` and `accounting_result` paths.

### Business logic
- `PaymentTaskHandler` dispatches by definition code — `app/Services/Payments/PaymentTaskHandler.php`
- Proof required; `quotation_locked` enforced; project stage → `payment` on installment 1 — handler + tests
- Journal attach on accept; shared daily journal — `JournalService`, `JournalWorkflowService`
- Amount snapshots on submit — `journal_payment.amount_snapshot`, scenario 8 test
- `OpenDailyJournal` scheduled 08:00 Cairo — `app/Jobs/OpenDailyJournal.php`, `routes/console.php`

### API (Plan 2 scope)
| Endpoint | File |
|---|---|
| `POST /projects/{id}/payments` | `PaymentController::storeForProject` |
| `GET /payments`, `GET /payments/{id}` | `PaymentController` |
| `GET /journals`, `GET /journals/{date}` | `JournalController` |
| `POST /attachments`, `GET /attachments/{id}` | `AttachmentController` |
| `POST /tasks/{id}/attachments` | `TaskController::attach` |
| Task complete returns `meta.created_tasks` | `TaskController::complete`, `CreatedTaskResource` |
| `TaskResource` exposes `form_schema`, `previous_outputs`, `subject` (payment) | `app/Http/Resources/TaskResource.php` |

### Frontend (Plan 2 scope)
| Screen | Route | File |
|---|---|---|
| Login | `/login` | `src/app/(without-layouts)/login/page.tsx` |
| Queue | `/queue` | `src/app/(with-layouts)/queue/page.tsx` |
| My Tasks | `/my-tasks` | `src/app/(with-layouts)/my-tasks/page.tsx` |
| Task Detail | `/tasks/[id]` | `src/app/(with-layouts)/tasks/[id]/page.tsx`, `src/components/tasks/task-detail-view.tsx` |
| Auth guard + token storage | — | `src/components/auth/auth-guard.tsx`, `src/context/auth-context.tsx`, `src/config/axios.ts` |
| Handover message on complete | — | `task-detail-view.tsx` `buildHandoverMessage()` |
| Attachment upload before complete | — | `task-detail-view.tsx`, `src/services/attachmentService.ts` |

---

## ⚠️ Partial

| Gap | Spec | Implementation | Missing |
|---|---|---|---|
| **Dynamic form rendering** | `03-data-model.md` §5.1, screen 02 §4 | `task-detail-view.tsx` `DynamicField` | Seeder uses field **`key`**; frontend reads **`name`** — forms render blank/wrong |
| **Reception payment context** | workflow 01 §2.2, screen 02 §3 | API `TaskResource::buildSubjectContext()` returns proof, amount, salesperson | Frontend **does not render `subject`**; relies on `previous_outputs` only |
| **Select/boolean/money field types** | form_schema types | Only text, number, textarea | No select, boolean, money, date widgets |
| **Queue UX** | screen map §4 | List with link to detail | No **Claim** button on card; no project/priority/overdue filters; no 409 race message |
| **My Tasks UX** | screen map §3 | Flat list | No Overdue/Today/Blocked grouping |
| **Task detail actions** | screen 02 §6 | Claim, Start, Complete | No Release, Pause, Block, Unblock, Comment |
| **Task detail activity** | screen 02 §7 | — | No status timeline or comment thread |
| **Attachment UX** | screen 02 §5 | Upload + required indicator | No inline proof preview; no replace; upload only in `in_progress` (not before claim) |
| **Navigation** | screen map §Navigation | Queue + My Tasks added | Still shows TailGrids demo items (Charts, UI Elements, etc.); **not permission-gated** — `src/components/common/sidebar/data.tsx` |
| **List states** | screen map cross-cutting | loading, empty, error | No **no-permission** state |
| **Journal screen** | screen map §15, workflow 01 §2.3 | API read endpoints exist | No `/journal` frontend route |
| **Start payment from UI** | Plan 2 demo | API `POST /projects/{id}/payments` | No project UI to trigger payment (demo incomplete) |
| **Enum labels locale** | `08-api-contract.md` §1 `Accept-Language` | EnumController returns labels | Frontend does not send locale header consistently |
| **`review_note` required when query** | workflow 01 §2.2 | Enforced server-side in handler | Form schema marks `review_note` as `required: false` |

---

## ❌ Missing or wrong

| Item | Spec | Evidence |
|---|---|---|
| **`payment.queried` activity event** | `10-notifications-and-activity-stream.md` | Not recorded in `PaymentTaskHandler` on reception query |
| **`journal.submitted` / `journal.reopened` activity events** | `10-notifications-and-activity-stream.md` | Not emitted (audit row exists for reopen in tests) |
| **Journal API submit/reopen** | `08-api-contract.md` §5 | `POST /journals/{date}/submit`, `/reopen` not routed (workflow uses task complete only) |
| **i18n / RTL** | `12-i18n-and-rtl.md`, screen map §1 Login | No ar/en switch before login; no RTL layout |
| **Permission-gated UI** | `04-permissions-and-roles.md`, screen map | Auth stores permissions but sidebar/routes ignore them |
| **Plan 2 demo checklist** | `plans/2026-08-25-phase2-payment-flow.md` §Definition of done | "Demo: login as Sales → … → accounting" still **unchecked** |
| **Local field validation before complete** | screen 02 §Completing | Frontend posts without client-side required-field check |

---

## 🔜 Correctly deferred

| Item | Spec / plan | Notes |
|---|---|---|
| Odoo quotation lock verification | workflow 01 §2.1 | Phase 1 checkbox + `quotations.locked_at` only |
| `odoo_journal_ref` / `odoo_reference` required | workflow 01 | Optional in Phase 1 per `[CONFIRMED] A5` |
| Projects list/detail screens | screen map §6–7 | Not in Plan 2 scope |
| Control Room, activity feed UI | screen map §2, §16 | Plan 5 |
| Sample/site/formula task codes in seeders | workflows 02–05 | Plans 3–4 (not Plan 2) |

---

# Cross-cutting checks (requested)

## Task definition codes (seeders vs workflow specs)

| Workflow | Template code | Plan | Seeder | Status |
|---|---|---|---|---|
| 01 Payment | `payment_cycle` | 2 | `PaymentWorkflowSeeder.php` | ✅ All 6 codes match |
| 02 Sample | `sample_request` | 3+ | Not on staging | 🔜 Deferred |
| 03 Modification | `sample_modification` | 3+ | Not on staging | 🔜 Deferred |
| 04 Formula | (within sample flow) | 3+ | Not on staging | 🔜 Deferred |
| 05 Site visit | `site_visit` | 4+ | Not on staging | 🔜 Deferred |

## API routes (`routes/api.php` vs `specs/08-api-contract.md`)

**Implemented (22 routes):** auth×3, tasks×9, attachments×2, payments×3, journals×2, enums×1, task-attachments×1

**Missing for Plans 1–2 relevance:**
- Tasks: `resume`, `unblock`, `comments`, `reassign`, `deadline`, ad-hoc create
- Journals: `submit`, `reopen`
- Files: `DELETE /attachments/{id}`
- Options: all 7 `/options/*` endpoints
- Time: all §8 endpoints

**Missing for later plans (correctly deferred):** projects, samples, site, stream, activity, notifications, dashboards, search, admin, audit-logs

## Frontend routes vs `specs/09-screens/00-screen-map.md`

| Spec screen | Expected | Staging | Status |
|---|---|---|---|
| Login | `/login` | `/login` | ✅ |
| My Tasks | `/my-tasks` | `/my-tasks` | ⚠️ Partial UX |
| Queue | `/queue` | `/queue` | ⚠️ Partial UX |
| Task Detail | `/tasks/[id]` | `/tasks/[id]` | ⚠️ Partial UX |
| Control Room | `/control-room` | — | 🔜 Plan 5 |
| Projects | `/projects` | — | 🔜 |
| Samples | `/samples` | — | 🔜 Plan 3 |
| Site | `/site` | — | 🔜 Plan 4 |
| Journal | `/journal` | — | ❌ Missing (API ready) |
| Workshop | `/workshop` | — | 🔜 |
| Activity | `/activity` | — | 🔜 Plan 5 |
| Admin | `/admin/*` | — | 🔜 Plan 6 |
| Demo pages | — | `/charts/*`, `/ui-elements/*`, etc. | ❌ Should not ship |

## Permission names vs `specs/04-permissions-and-roles.md`

- **61/61 permission strings** present in `ReferenceSeeder::PERMISSIONS` ✅
- **Role matrix** for all 10 non–super-admin roles matches spec §3 exactly ✅
- **`payment.skip_proof`**: super_admin only ✅ (`ReferenceSeederTest`)
- **Policies** check permissions, not role names ✅ (`TaskPolicy`, `PaymentPolicy`, etc.)
- **Frontend** does not gate navigation or actions by permission ❌

---

# Top 10 critical gaps (Plans 1–2)

1. **Frontend `form_schema` uses `field.name` but API sends `field.key`** — dynamic payment/sample forms cannot submit required fields from the UI (`PaymentWorkflowSeeder.php` vs `task-detail-view.tsx`).

2. **Reception payment review screen omits `subject` context** — spec requires proof, amount, salesperson inline without re-entry; API provides `data.subject.attachments` but frontend never renders it (`TaskResource.php` vs `task-detail-view.tsx`).

3. **Permission-gated navigation not implemented** — all users see TailGrids demo sidebar; spec requires hiding Control Room, Admin, etc. by permission (`09-screens/00-screen-map.md` vs `sidebar/data.tsx`).

4. **Missing task lifecycle API endpoints** — no `resume`, `unblock`, `comment`, `reassign`, or deadline override despite spec §3 and screen 02 action table (`routes/api.php`).

5. **No Idempotency-Key support** — spec §1 requires safe double-tap on claim/complete; not implemented anywhere in API middleware or controllers.

6. **Task detail UI missing core actions** — no Release, Pause, Block, Unblock, or activity timeline; blocks Plan 1 task lifecycle from being operable via UI (`09-screens/02-task-detail.md`).

7. **Queue does not match spec** — cards link to detail instead of primary **Claim** action; no handling of 409 `task.already_claimed` on list (`09-screens/00-screen-map.md` §4).

8. **Incomplete enum/options catalog** — frontend cannot load select options from `/options/*`; many enums from spec §11b absent (`EnumController.php`).

9. **`payment.queried` and journal activity events not recorded** — payment query path silent in activity stream; breaks ops visibility spec (`10-notifications-and-activity-stream.md`).

10. **End-to-end payment demo not delivered** — Plan 2 definition-of-done demo and Journal screen remain unchecked; no UI to start a payment or view daily journal (`plans/2026-08-25-phase2-payment-flow.md`).

---

# Appendix: spec documents read

| Document | Relevance to Plans 1–2 |
|---|---|
| `specs/README.md` | Index |
| `specs/00-overview-and-glossary.md` | Departments, vocabulary |
| `specs/03-data-model.md` | Tables, tasks, workflow |
| `specs/04-permissions-and-roles.md` | Permissions audit |
| `specs/05-workflow-engine.md` | Engine scenarios |
| `specs/06-task-and-time-tracking.md` | Task lifecycle, calendar |
| `specs/07-workflows/01-payment-to-accounting.md` | Plan 2 primary workflow |
| `specs/07-workflows/02–05` | Deferred workflows |
| `specs/08-api-contract.md` | Route audit |
| `specs/09-screens/00-screen-map.md`, `02-task-detail.md` | Frontend audit |
| `specs/10-notifications-and-activity-stream.md` | Activity gaps |
| `specs/11-audit-and-exceptions.md` | Audit gaps |
| `specs/12-i18n-and-rtl.md` | i18n gaps |
| `specs/13-odoo-gateway-and-seed-data.md` | Seed/settings reference |
| `specs/14-phase-2-backlog.md` | Deferrals |
| `specs/16-sla-defaults.md` | Payment SLA values in seeder |
| `plans/2026-08-22-phase1-spine.md` | Plan 1 scope |
| `plans/2026-08-25-phase2-payment-flow.md` | Plan 2 scope |

---

*Audit only — no code fixes applied.*
