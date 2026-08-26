# Colortek Phase 1 — Site Visit and Readiness Vertical Slice — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax.

**Goal:** A site engineer conducts a visit on mobile — measurements, the five-item condition statement, signed scan — sets readiness, and the system holds or releases site work accordingly. Corrective actions route to the right queue; re-inspection prefills measurements and releases held tasks when ready. Visible in Queue, My Tasks, Task Detail, and the dedicated site visit form.

**Depends on:** Plan 1 (spine) — merged. Plan 2 (payment flow) — merged for auth, attachments API, Queue, My Tasks, and Task Detail. Plan 3 is **not** a hard dependency: site and sample workflows run in parallel per `A7`; this plan can ship before or after Plan 3.

**Architecture:** Extend the Laravel API with site tables, the `site_visit` workflow template, task handlers keyed by `workflow_task_definitions.code`, a `SiteBlockService` for hold/release/override, and bulk measurement upsert. Next.js adds a client-only site visit form with an offline draft layer; readiness and corrective-action tasks reuse Task Detail with workflow-specific forms.

**Spec:** `specs/07-workflows/05-site-visit-and-readiness.md`, `specs/03-data-model.md` §9, `specs/08-api-contract.md` §7 & §11b, `specs/09-screens/05-site-visit-form.md`, `specs/09-screens/03-project-detail.md` § Site (read-only summary deferred to Plan 5), `specs/05-workflow-engine.md` §8–9, `specs/06-task-and-time-tracking.md` §1, `specs/04-permissions-and-roles.md` §2 Site, `specs/16-sla-defaults.md`, `specs/15-engineering-standards.md` §B5–B6

**Working directory:** `/workspace/colortek-api` (API), `/workspace/colortek-frontend` (UI)

**Phase 1 scope notes (explicit deferrals):**
- **In scope:** capture, display, and print measurements; no area calculation, no material quantity (`07-workflows/05` §5, `[TBD]` C6). `area_sqm` column exists but stays empty/read-only in UI.
- **In scope:** offline local draft, queued photo upload, bulk measurement sync with `Idempotency-Key` (`09-screens/05` § Offline).
- **Deferred to Plan 5:** Project Detail Site tab, live workflow strip, SSE activity for site events (Plan 5 live layer).
- **Deferred to Plan 6:** Admin CRUD for `site_checklist_items` and `humidity_max` setting UI (seed defaults in this plan; admin edit later).
- **Ask client before pilot:** default value for `humidity_max` — implement the warning UI but leave the setting null until confirmed (`03-data-model.md` §9).

---

## Task 1: Site data model and checklist seed

**Files:**
- Migrations: `site_checklist_items`, `site_visits`, `site_visit_answers`, `site_measurements`, `site_measurement_deductions`, `corrective_actions`
- Models + factories for each; extend `Project` with `siteVisits`, `block_all_when_site_not_ready`
- `database/seeders/SiteChecklistSeeder.php` — five items from `03-data-model.md` §9 (exact Arabic labels, `is_readiness_critical` on items 2–4)
- Seed `settings` keys: `block_all_when_site_not_ready` (default `false`), `humidity_max` (default `null` until client confirms)

**Check:** `php artisan migrate:fresh --seed` passes; five checklist items exist; factory creates a visit with measurements and deductions including `sign = add`.

---

## Task 2: `site_visit` workflow template

**Files:**
- `database/seeders/SiteVisitWorkflowSeeder.php` (called from `ReferenceSeeder` or `DatabaseSeeder`)
- Task definitions: `site_conduct_visit`, `site_set_readiness`, `corrective_action_task`, `site_reinspection`
- `form_schema` and `required_attachment_types` per `07-workflows/05` §3
- Set `blocks_when_site_not_ready = true` on site execution task definitions that exist in other templates (or document which codes get the flag when those templates land)
- Transitions per `07-workflows/05` §6: visit → readiness; readiness → corrective (conditional, one per failed item); all corrective resolved → reinspection (`join_mode = all`); readiness ready → instance completes
- SLAs from `specs/16-sla-defaults.md`

**Check:** `WorkflowTemplate::where('code','site_visit')->where('is_active',true)->exists()`; four task definitions seeded with correct departments.

---

## Task 3: Site visit lifecycle service

**Files:**
- `app/Services/Site/SiteVisitService.php` — create visit, assign reference `{project}-SV{n}`, compute `visit_number` and `parent_visit_id` for re-inspections
- Prefill header from project: `project_name_on_form`, `address_on_form`, `quotation_number_on_form`, `engineer_user_id`
- Draft PATCH while `submitted_at` is null; submit locks visit (`submitted_at` set)
- `app/Policies/SiteVisitPolicy.php` — `site.visit_create`, `site.visit_submit`, `site.measurements_edit` (post-submit edits audited)
- Hook `POST /projects/{id}/site-visits` to create `SiteVisit` row + start `site_visit` workflow instance scoped to `site_visit`

**Check:** HTTP test: start visit → `site_conduct_visit` task appears in site queue with prefilled header; submit without signed scan returns 422.

---

## Task 4: Measurement sheet — bulk upsert and element grouping

**Files:**
- `app/Services/Site/SiteMeasurementService.php`
- `POST /site-visits/{id}/measurements` — single bulk upsert of rows + nested deductions; accepts `Idempotency-Key`
- Logic: continuation rows (`element_name` blank) inherit `element_group_id` from the named row above; preserve `page_number`, `line_number`, order; store deduction `count`, dimensions, `sign` (`subtract`/`add`), `label`
- `area_sqm` accepted but never computed — leave null
- `SiteMeasurementResource`, form request validation

**Check:** Feature test scenarios 4–7 from `07-workflows/05` §7 (addition sign, count prefix, continuation rollup, 40 rows two pages save and reload in order).

---

## Task 5: Condition statement capture

**Files:**
- Save answers on submit (and on draft PATCH for resilience): `site_visit_answers` linked to `site_checklist_items`
- Each answer: `answer_value` json, `passed` boolean for yes/no items, `note` (notes box always stored, even empty)
- Humidity: store numeric value; if `humidity_max` setting is set and value exceeds it, return a non-blocking warning flag in the response — does **not** force `not_ready`
- Attachments: require `site_report_signed` on submit; optional `site_photo` (any number)
- Audit log entry when `site.measurements_edit` unlocks a submitted visit

**Check:** Scenario 1 from spec §7 — all five items and notes boxes persist; scenario 8 — submit rejected without signed scan.

---

## Task 6: Task completion handlers — visit, readiness, corrective, reinspection

**Files:**
- `app/Services/Site/SiteVisitTaskHandler.php` — dispatches by task definition code; hooked from `TaskService::complete()`
- **`site_conduct_visit`:** validate visit submitted and locked; advance to `site_set_readiness`
- **`site_set_readiness`:** read `readiness` + `summary`; if any critical checklist item is "no", force `not_ready` regardless of form selection (scenario 2); update `site_visits.readiness`; on `ready` call release (Task 7); on `not_ready` call apply block + spawn corrective actions (one task per failed critical item, `responsible_party` routing per §3.3 — client → Sales queue)
- **`corrective_action_task`:** save `resolution_note`, optional photo; mark corrective action `resolved`; when all on visit resolved, engine creates `site_reinspection`
- **`site_reinspection`:** create new `site_visit` with `visit_number + 1`, `parent_visit_id`, copy measurements from parent, new workflow instance

**Check:** Handover meta on complete names the next task; corrective action for `client` party lands in Sales queue (scenario 13).

---

## Task 7: Site blocking, release, and override

**Files:**
- `app/Services/Site/SiteBlockService.php`
- **`applyBlock($project)`:** tasks with `blocks_when_site_not_ready = true` and status in `ready|claimed|in_progress|paused` → `pending`; respect `projects.block_all_when_site_not_ready` OR `settings.block_all_when_site_not_ready` to hold **all** open tasks (scenarios 9–10)
- **`releaseBlock($project)`:** held tasks return to `ready` automatically (scenario 12)
- **`override($task, $user, $reason)`:** permission `site.override_block`; release exactly one task; write `audit_logs` row `event = override`; write `warning` activity event (scenario 11)
- `POST /tasks/{id}/override-site-block` endpoint
- Extend Task Detail resource: include site-block context (visit reference, failed items, open corrective count) for `pending` site-held tasks

**Check:** Unit/feature tests for scenarios 9–12; workshop task on same project remains claimable when default block applies.

---

## Task 8: Site visit and corrective action read/write APIs

**Files:**
- `SiteVisitController`, `CorrectiveActionController`, `SiteChecklistItemController`
- Routes per `08-api-contract.md` §7: list/show/create visit, draft PATCH, measurements bulk, submit, readiness, PDF, checklist items, corrective actions CRUD
- `GET /projects/{id}/site-visits` for project context
- Enum catalog entries: `site_readiness`, `corrective_action_status`, `responsible_party`; options `/options/checklist-items`
- Extend `TaskResource` subject context for site visit tasks (visit header, measurement summary, condition answers, corrective actions)

**Check:** HTTP test: full happy path API — create visit → bulk measurements → submit → complete conduct task → complete readiness `not_ready` → corrective tasks created → complete all → reinspection task → new visit prefilled.

---

## Task 9: Site visit PDF (print)

**Files:**
- `app/Services/Site/SiteVisitPdfGenerator.php` (or Blade view)
- `GET /site-visits/{id}/pdf` — reproduces paper form layout: header, measurement table with deductions inline, condition statement with exact Arabic wording, page numbers
- Phase 1: store/render only; no computed areas

**Check:** PDF generates for a visit with 40 rows across two pages; row order and page numbers match input (scenario 7 print side).

---

## Task 10: Site visit flow feature tests

**Files:** `tests/Feature/SiteVisitFlowTest.php` — all 14 scenarios from `07-workflows/05` §7

**Check:** `php artisan test --filter=SiteVisitFlowTest`

---

## Task 11: Frontend — site visit API layer and types

**Files:**
- `src/services/siteVisitService.ts`, `src/services/correctiveActionService.ts`
- `src/types/siteVisit.ts` — visit, measurement row, deduction, checklist answer, sync status
- Query keys in `src/lib/queryKeys.ts`

**Check:** Types compile; service methods match API contract §7.

---

## Task 12: Frontend — Site Visit Form (measurements + condition)

**Route:** `/site-visits/[id]/edit`
**Spec:** `specs/09-screens/05-site-visit-form.md`

**Files:**
- Client component page (per `15-engineering-standards.md` §B5)
- Two-step stepper: Measurements → Site condition → Review & submit
- Header fields editable, prefilled from API
- Desktop: grid matching paper columns; mobile: one-row-at-a-time list with Add row
- Deduction input: `[count] ( [length] × [width] ) [−/+] [label]`; default sign `−`
- Speed: remember last element name, persist width between rows, numeric keypad, running row/page count
- Condition: five items from API labels (RTL Arabic exact wording); large Yes/No buttons; notes always visible; inline "will mark Not Ready" warning on critical failures
- Humidity warning when over threshold (non-blocking)
- Signed scan upload + client signatory name; link from `site_conduct_visit` task Detail

**Check:** Form saves draft to server via PATCH; bulk measurements POST on step navigation or explicit save; submit locks visit and completes conduct task handover to readiness task.

---

## Task 13: Frontend — offline draft and photo queue

**Spec:** `09-screens/05-site-visit-form.md` § Offline, `08-api-contract.md` §7

**Files:**
- `src/lib/siteVisitDraftStore.ts` — IndexedDB (preferred) or localStorage; persists full form on every change
- `src/lib/offlinePhotoQueue.ts` — queue photos locally; upload via existing attachment service when online
- Sync status banner: "Saved on this device HH:MM" vs "Synced with server"
- Bulk measurements push with `Idempotency-Key` on reconnect
- Submit and task-complete refused while offline with clear message; draft retained

**Deferred (not Phase 1):** service-worker background sync, conflict resolution beyond last-write-wins — spec requires local draft + reconnect upload, not full CRDT sync.

**Check:** DevTools offline mode — edits persist across reload; photos queue and upload on reconnect; submit blocked offline; bulk sync succeeds in one request after reconnect.

---

## Task 14: Frontend — readiness, blocking panel, corrective actions, re-inspection

**Spec:** `specs/09-screens/02-task-detail.md` § Site-blocked tasks, `07-workflows/05` §3.2–3.4

**Files:**
- Extend `task-detail-view.tsx` (or task-type subcomponents):
  - **`site_set_readiness`:** select ready/not_ready, summary required when not_ready; disable ready when critical items failed
  - **`corrective_action_task`:** resolution note + optional photo; show responsible party
  - **`site_reinspection`:** Complete opens new visit form prefilled with parent measurements
  - **`pending` site-held tasks:** blocking panel with visit reference, reason, corrective count; override button gated on `site.override_block` with reason dialog
- i18n: RTL layout for Arabic checklist labels from API, not hardcoded in messages files (`15-engineering-standards.md` §B6)

**Check:** Demo path — conduct visit → not_ready → site task shows held panel, workshop task still claimable → complete corrective (Sales queue for client party) → reinspection → ready → held tasks return to queue without manual unlock.

---

## Definition of done

- [ ] `composer qa` passes (API)
- [ ] `SiteVisitFlowTest` — 14 scenarios green
- [ ] Demo: login as site engineer → fill visit on phone form → upload signed scan → set not_ready → Sales sees corrective action → resolve → re-inspect with prefilled measurements → set ready → held site tasks release
- [ ] Offline draft and photo queue verified manually (offline → edit → reload → reconnect → sync)
- [ ] Frontend lint passes; site visit form, Task Detail extensions wired to API
- [ ] PDF endpoint produces readable output matching paper form structure
