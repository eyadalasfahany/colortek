# Colortek Phase 1 — Samples and Formula Vertical Slice — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Sales raises a sample request → Reception forwards → Manager approves → Workshop makes the sample (timer) while Tinting authors the formula in parallel → Reception registers → Sales prints the client approval form, gets it signed, uploads it — visible in Queue, My Tasks, Task Detail, and Sample Detail with the full attempt chain. Modifications create a linked new sample, not an edit of the old one.

**Depends on:** Plan 1 (spine) and Plan 2 (payment flow) — merged in PRs #1 and #2.

**Architecture:** Extend the Laravel API with `samples`, `sample_approvals`, and `formulas` tables; seed `sample_request` and `sample_modification` workflow templates; task-completion handlers keyed by `workflow_task_definitions.code` (same pattern as `PaymentTaskHandler`); bilingual PDF generation for the client approval form. Next.js app extends existing Task Detail and adds Sample Detail with chain panel.

**Spec:** `specs/07-workflows/02-sample-request-and-approval.md`, `specs/07-workflows/03-sample-modification.md`, `specs/07-workflows/04-formula-registration.md`, `specs/03-data-model.md` §8 & §11, `specs/08-api-contract.md` §6, `specs/09-screens/04-sample-detail.md`, `specs/09-screens/00-screen-map.md` §8–10 & §14, `specs/16-sla-defaults.md`, `specs/04-permissions-and-roles.md` §Samples and formula, `specs/02-architecture.md` §Samples

**Working directory:** `/workspace/colortek-api` (API), `/workspace/colortek-frontend` (UI)

---

## Task 1: Sample, formula, and approval data model

**Files:**
- Migrations: `samples`, `sample_approvals`, `formulas`
- Enums: `SampleStatus`, `FormulaStatus`, `SampleApprovalType`
- Models + factories: `Sample`, `SampleApproval`, `Formula`
- `app/Services/Samples/SampleReferenceGenerator.php` — `{project}-S{seq}` or `CL-{client_id}-S{seq}` for pre-sale; `{sample}-F{version}` for formulas (`specs/03-data-model.md` §11)
- Extend `Project` with `samples()` relation; extend `Attachment` morph targets for sample/formula
- Setting seed: `sample.default_size`, `sample.repeat_attempt_threshold` = 4 (`specs/07-workflows/03` §4)

**Check:** `php artisan migrate:fresh --seed` passes; factories create a sample with `attempt_number = 1`, `root_sample_id = id`.

- [ ] `samples.client_id` is NOT NULL; `project_id` nullable
- [ ] Status enum covers all 10 values from spec §8
- [ ] `FormulaFactory` requires at least `body` or a `formula_sheet` attachment

---

## Task 2: `sample_request` workflow template

**Files:**
- `database/seeders/SampleWorkflowSeeder.php` (called from `ReferenceSeeder`)
- Template: `sample_request`, scope `sample`, version 1
- Task definitions (11 codes):

| Code | Dept | Timer | SLA (min) | Escalate (min) | Notes |
|---|---|---|---|---|---|
| `sales_create_sample_request` | sales | no | none | — | entry point |
| `reception_review_sample_request` | reception | no | 240 | 480 | |
| `sales_fix_sample_request` | sales | no | 240 | 480 | return path |
| `manager_approve_sample` | management | no | 240 | 480 | permission `sample.approve_manager` |
| `sales_sample_rejected` | sales | no | none | — | terminal on manager reject |
| `workshop_make_sample` | workshop | **yes** | 1440 (3d) | 2400 (5d) | `blocks_when_site_not_ready = false` |
| `tinting_author_formula` | tinting | **yes** | 480 (1d) | 960 (2d) | permission `formula.author` |
| `reception_register_formula` | reception | no | 240 | 480 | join `all` on both predecessors |
| `sales_get_client_decision` | sales | no | 2400 (5d) | 4800 (10d) | initial status `waiting` |

- Transitions per `specs/07-workflows/02` §3 and `specs/07-workflows/04` §5 — manager approval creates **both** `workshop_make_sample` and `tinting_author_formula`; `reception_register_formula` uses `join_mode = all`
- Each definition gets `form_schema`, `required_fields`, `required_attachment_types` from workflow specs §2

**Check:** `WorkflowTemplate::where('code','sample_request')->where('is_active',true)->exists()`; parallel transitions from `manager_approve_sample` to workshop and tinting both present.

- [ ] No path from Reception to Workshop skips `manager_approve_sample`
- [ ] `reception_register_formula` transition has `join_mode = all`

---

## Task 3: `sample_modification` workflow template

**Files:**
- `database/seeders/SampleModificationWorkflowSeeder.php`
- Template: `sample_modification`, scope `sample`, version 1
- Single task definition: `sales_create_modification_request` (permission `sample.request_modification`)
- Form fields: `modification_reason`, `color`, `texture`, `client_reference`, `size`, `finish_requirement`, `needed_by` — prefilled from parent on API, editable on form

**Check:** Template exists; completing `sales_create_modification_request` starts a new `sample_request` instance on the child sample entering at `reception_review_sample_request` (not `sales_create_sample_request`).

- [ ] Child sample gets `parent_sample_id`, `root_sample_id`, `attempt_number = parent + 1`
- [ ] Parent status → `superseded`; parent fields otherwise unchanged

---

## Task 4: Sample and formula task completion handlers

**Files:**
- `app/Services/Samples/SampleTaskHandler.php` — dispatches by task definition code (mirror `PaymentTaskHandler`)
- Hook from `TaskService::complete()` alongside existing `PaymentTaskHandler`
- `app/Services/Samples/SampleService.php` — start request, reference generation, status transitions, project stage
- `app/Services/Samples/FormulaService.php` — author, register, approve, supersede, audited correction
- `app/Services/Samples/SampleChain.php` — count by `root_sample_id`, build chain payload

**Rules by code:**

| Code | Key behaviour |
|---|---|
| `sales_create_sample_request` | Requires `client_id`; pre-sale needs `sample.create_presale`, sets `is_presale`; creates sample + `sample_approvals` not yet; status → `pending_manager_approval` |
| `reception_review_sample_request` | `return_to_sales` requires note |
| `manager_approve_sample` | Writes `sample_approvals` row `type=manager`; approved → status `in_workshop`; rejected → `rejected_by_manager` |
| `workshop_make_sample` | Requires authored formula exists; stops timer; requires `sample_photo` attachment; status → `awaiting_formula_registration` |
| `tinting_author_formula` | Creates `formulas` row `status=draft`, version increment; requires `body` or `formula_sheet`; records `author_employee_id`, `authored_at` |
| `reception_register_formula` | Formula `draft` → `registered`; sets `registered_by_user_id`, `registered_at`; corrections append with audit row; sample → `ready_for_client_approval` |
| `sales_get_client_decision` | Requires `client_approval_form` attachment; `decided_at` from form not upload time; approved → sample `approved`, formula `approved`, `approved_formula_id` set, `sample_approvals` `type=client`; rejected → `rejected_by_client`, **no** auto-modification |
| `sales_create_modification_request` | Only from `rejected_by_client`; creates child sample; activity `sample.modification_requested` severity `warning`; attempt ≥ threshold → manager task priority `high` |

**Check:** Feature tests 1–6 from `specs/07-workflows/02` §5 pass in isolation.

- [ ] Workshop complete blocked when no formula authored (scenario 5)
- [ ] Client decision blocked without signed form (scenario 7)
- [ ] `decided_at` stored separately from upload timestamp (scenario 8)

---

## Task 5: Sample and formula REST APIs

**Files:**
- `SampleController`, `FormulaController`, `SamplePolicy`, `FormulaPolicy`
- `SampleService`, `FormulaService` (read paths)
- Resources: `SampleResource`, `SampleListResource`, `SampleChainResource`, `FormulaResource`, `SampleApprovalResource`
- Extend `TaskResource` subject context for `Sample` — colour, texture, reference, pre-sale marker, parent/rejection reason for workshop
- Routes (`specs/08-api-contract.md` §6):

| Method | Path | Permission |
|---|---|---|
| GET | `/api/v1/samples` | `sample.view` |
| POST | `/api/v1/samples` | `sample.create` — creates sample + starts `sample_request` instance |
| GET | `/api/v1/samples/{id}` | `sample.view` |
| GET | `/api/v1/samples/{id}/chain` | `sample.view` |
| GET | `/api/v1/projects/{id}/samples` | `sample.view` |
| POST | `/api/v1/samples/{id}/modification` | `sample.request_modification` |
| POST | `/api/v1/samples/{id}/approval-form` | `sample.record_client_decision` |
| POST | `/api/v1/samples/{id}/client-decision` | `sample.record_client_decision` |
| GET | `/api/v1/samples/{id}/formulas` | `formula.view` |
| POST | `/api/v1/samples/{id}/formulas` | `formula.author` |
| POST | `/api/v1/formulas/{id}/register` | `formula.register` |
| PATCH | `/api/v1/formulas/{id}` | `formula.update_registered` — audited |

**Check:** HTTP test: `POST /samples` → sales task in queue with `form_schema`; `GET /samples/{id}/chain` returns ordered attempts with status, rejection reason, formula ref.

- [ ] List endpoints paginate, default `per_page=15`
- [ ] Pre-sale sample without `project_id` returns 403 without `sample.create_presale`

---

## Task 6: Client approval form PDF

**Files:**
- `app/Services/Samples/ApprovalFormGenerator.php` — bilingual PDF per `specs/07-workflows/02` §4
- Blade/view template: header, project/client/quotation, sample reference (large), colour/texture/size/finish, attempt number + previous rejection if applicable, decision boxes (موافق / غير موافق), signature lines, comments area
- `POST /api/v1/samples/{id}/approval-form` returns PDF stream; sets `sample_approvals.form_generated_at` on the pending client approval row

**Check:** HTTP test returns `Content-Type: application/pdf`; `form_generated_at` populated.

- [ ] PDF includes attempt number and previous sample reference when `attempt_number > 1`
- [ ] Pre-sale samples show clear marker, no project name

---

## Task 7: Sample flow feature tests

**Files:**
- `tests/Feature/SampleRequestFlowTest.php` — **11 scenarios** from `specs/07-workflows/02` §5
- `tests/Feature/SampleModificationFlowTest.php` — **10 scenarios** from `specs/07-workflows/03` §6
- `tests/Feature/FormulaRegistrationFlowTest.php` — **10 scenarios** from `specs/07-workflows/04` §6

**Check:** `php artisan test --filter='SampleRequestFlowTest|SampleModificationFlowTest|FormulaRegistrationFlowTest'` — **31 scenarios** green.

- [ ] Parallel branch: approval creates workshop + tinting tasks in different queues (formula §6.1)
- [ ] Registration stays `waiting` until both complete (formula §6.2–3)
- [ ] Attempt 4 raises manager approval priority (modification §6.10)

---

## Task 8: Frontend — sample API layer

**Files:**
- `src/services/sampleService.ts` — list, get, chain, start, modification, approval-form download, client-decision
- `src/services/formulaService.ts` — list, author, register, patch
- Extend `src/types/api.ts` — `Sample`, `SampleChainEntry`, `Formula`, `SampleApproval`, sample status enums
- Extend `src/lib/queryKeys.ts`

**Check:** Types match API resources; services use existing axios config and auth.

- [ ] `sample_status` and `formula_status` enums match API `/enums/*` if exposed

---

## Task 9: Frontend — Task Detail extensions for sample/formula tasks

**Route:** `/tasks/[id]` (existing)
**Spec:** `specs/09-screens/00-screen-map.md` §10, `specs/09-screens/02-task-detail.md`

**Files:**
- Extend `src/components/tasks/task-detail-view.tsx`:
  - Render sample subject context (colour, texture, reference photo, pre-sale banner, parent/rejection for workshop)
  - Employee picker for `author_employee_id` on `tinting_author_formula`
  - Formula registration panel on `reception_register_formula` — side-by-side authored text + scanned sheet, previous version if repeat attempt
  - **Print** button on `sales_get_client_decision` → `POST /samples/{id}/approval-form`, download PDF
  - Date field for `decided_at` (label: date on the form, not today)
- Reuse existing dynamic form, attachment upload, claim/start/complete, handover message patterns from Plan 2

**Check:** Complete tinting task with formula sheet → registration task shows authored content; Print on client-decision task downloads PDF.

- [ ] `sales_get_client_decision` requires `client_approval_form` upload before Complete enabled
- [ ] Workshop task shows parent reference + rejection reason when modification

---

## Task 10: Frontend — Sample Detail and chain UI

**Route:** `/samples/[reference]`
**Spec:** `specs/09-screens/04-sample-detail.md`

**Files:**
- `src/app/(with-layouts)/samples/[reference]/page.tsx`
- Components: `SampleChainPanel`, `SampleDetailSections` (requirement, photos side-by-side, formula with author vs registrar, approvals, hours placeholder, activity placeholder)
- `SampleChainThread` — newest first, current sample marked, rejection reasons inline, 4+ attempts marker
- Actions gated by permission: Print approval form, Record client decision, Request modification, Attach to project (pre-sale), Cancel
- Add Samples link to sidebar (`specs/09-screens/00-screen-map.md` §8 — list view can be a simple status-grouped page at `/samples` or deferred to Plan 5; minimum: detail page reachable from tasks and project)

**Check:** Chain of 3 attempts renders correctly; superseded sample shows "Replaced by …" banner; pre-sale marker visible.

- [ ] Read design tokens from `design-system/DESIGN-SYSTEM.md` — no hardcoded colours
- [ ] Formula block shows author and registrar as separate people
- [ ] Transcription correction shows original + correction, not overwrite

---

## Definition of done

- [ ] `composer qa` passes (API)
- [ ] `SampleRequestFlowTest` — 11 scenarios green
- [ ] `SampleModificationFlowTest` — 10 scenarios green
- [ ] `FormulaRegistrationFlowTest` — 10 scenarios green
- [ ] Demo: login as Sales → create sample → Reception forwards → Manager approves → Workshop + Tinting complete in parallel → Reception registers → Sales prints form, uploads signed scan, records approval
- [ ] Demo: client rejects → Sales creates modification → chain shows 2 attempts on Sample Detail
- [ ] Frontend lint passes; Task Detail and Sample Detail wired to API
