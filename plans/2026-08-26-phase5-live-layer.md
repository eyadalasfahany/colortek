# Colortek Phase 1 — Live Layer — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Managers open the Control Room and see what is happening and what is stuck — live. Every user gets an in-app notification centre. Project Detail becomes the single operational view for one job. Workshop, Site and Samples dashboards answer "what needs doing now?" in one request each.

**Depends on:**
- **Plan 1 (spine)** — merged. `ActivityRecorder`, `activity_events` table, `RecordTaskActivity` for task lifecycle events, audit log, Laravel events after commit.
- **Plan 2 (payment flow)** — merged. Projects/clients/payments models, Queue/My Tasks/Task Detail UI patterns, `payment.confirmed` activity from `PaymentTaskHandler`.
- **Plan 3 (samples & formula)** — required before building. Sample chains, manager approval, formula registration, sample task definitions. Without it, the Samples dashboard and Project Detail → Samples tab have no data.
- **Plan 4 (site visit & readiness)** — required before building. Site visits, readiness, corrective actions, crew logs. Without it, the Site dashboard, "Sites not ready" KPI, and Project Detail → Site/People tabs are empty.

**Architecture:** Activity rows and notifications are written by event listeners after commit — never inline in services (`specs/02-architecture.md` §2). SSE pushes filtered `activity_events` over `GET /api/v1/stream`; the frontend falls back to polling `GET /api/v1/activity?since=` every 15 s. Each dashboard endpoint returns a whole screen in one JSON payload (`specs/08-api-contract.md` §10). Next.js uses axios + React Query; shared feed/notification state feeds Control Room and Project Detail.

**Spec:** `specs/10-notifications-and-activity-stream.md`, `specs/09-screens/01-control-room.md`, `specs/09-screens/03-project-detail.md`, `specs/09-screens/00-screen-map.md` §6–14 & §16–21, `specs/08-api-contract.md` §4 & §9–10, `specs/02-architecture.md` §4 & §6, `specs/03-data-model.md` §10, `specs/04-permissions-and-roles.md` §4, `specs/14-phase-2-backlog.md`

**Working directory:** `/workspace/colortek-api` (API), `/workspace/colortek-frontend` (UI)

---

## Already built (Plan 1–2 — do not redo)

| Item | Location | Notes |
|---|---|---|
| `activity_events` table + model | migration, `ActivityEvent.php` | Immutable feed rows; `id` is the SSE event id |
| `ActivityRecorder::record()` | `app/Services/Activity/ActivityRecorder.php` | Writes `message_en` / `message_ar` at insert time |
| Task activity listener | `RecordTaskActivity.php` | `task.created`, `task.claimed`, `task.completed`, `task.blocked` |
| Payment activity | `PaymentTaskHandler.php` | `payment.confirmed` (inline call — migrate to listener in Task 1) |
| Audit log | Plan 1 Task 8 | Separate from feed; stays transactional |
| `ProjectSummaryResource` | stub only | Extend in Task 8 |

**Gap to close first:** verify `RecordTaskActivity` is registered for all four task events (Laravel auto-discovery expects `handle(Event $e)`, not `handleTaskCreated`). Register explicitly in `AppServiceProvider` or rename handlers before adding more listeners.

---

## Phase 2 deferrals (do not build in this plan)

| Item | Spec reference | Phase 1 behaviour |
|---|---|---|
| Email / WhatsApp notifications | `10-notifications` §3, `14-phase-2-backlog` §7 | `database` channel only |
| Per-user channel preferences, quiet hours, daily digest | `14-phase-2-backlog` §7 | Not in UI |
| Cost tab, material tab, AI insights on Project Detail | `03-project-detail` § "not on this screen", A2 | Leave tab space; no placeholder content |
| `activity_events.visible_to_permission` for cost events | `10-notifications` §1, A2 | Column exists; nothing sets it yet |
| Delivery stages (Material delivery, Final handover) | A34, `14-phase-2-backlog` §4 | Show Delivery greyed: "not configured yet" |
| Auto project completion (delivery + final payment) | A9 | Manual `project.complete` only |
| Report builder, export, scheduled reports | `14-phase-2-backlog` §9 | Dashboards only — "now", not "last quarter" |
| Redis / Reverb / WebSockets for live feed | `02-architecture` §4 | SSE over HTTP is sufficient for <30 users |

---

## Backend tasks

### Task 1: Activity infrastructure — scope, resource, broadcast hook

**Files:**
- `app/Services/Activity/ActivityQuery.php` — visibility filter from `04-permissions-and-roles.md` §4 + optional `visible_to_permission`
- `app/Http/Resources/ActivityEventResource.php` — id, type, severity, message (locale), actor, department, project summary, `link` from payload, created_at
- `app/Events/ActivityRecorded.php` — dispatched after every successful `ActivityRecorder::record()`
- Modify `ActivityRecorder.php` — dispatch `ActivityRecorded` after insert
- Modify `PaymentTaskHandler.php` — remove inline `activityRecorder->record()`; emit domain event instead (Task 2)
- Register `RecordTaskActivity` + future listeners in `AppServiceProvider` or `bootstrap/app.php` `withEvents()`

**Check:** Feature test: user A cannot see activity on a project they lack visibility to; user B with `project.view_all` can.

---

### Task 2: Expand activity listeners (Phase 1 event types)

**Files:**
- Extend `RecordTaskActivity.php` or add focused listeners for: `task.started`, `task.unblocked`, `task.reassigned`, `task.overdue` (from overdue job)
- `RecordPaymentActivity.php` — `payment.confirmed`, `payment.queried`, `journal.submitted`, `journal.reopened`
- `RecordSampleActivity.php` — all sample/formula types from `10-notifications` §1 (Plan 3 events)
- `RecordSiteActivity.php` — site visit, readiness, corrective action, override types (Plan 4 events)
- `RecordProjectActivity.php` — `project.stage_changed`, `project.completed`
- Each listener: render EN + AR messages at write time; set `payload` with `{ route, params }` for frontend links

**Check:** Completing a payment cycle produces `payment.confirmed`, `task.created`, `task.completed` lines with correct severity; sample rejection produces `warning`.

---

### Task 3: Activity read API

**Files:**
- `ActivityController.php` — `index()` with filters: `since`, `project_id`, `department_id`, `severity`, `type`
- `ProjectActivityController.php` or nested route — `GET /projects/{id}/activity`
- `ActivityFilter.php`
- Routes in `routes/api.php`

**Check:** `GET /api/v1/activity?since=100` returns only events with `id > 100` the caller may see; paginated, `per_page` 15.

---

### Task 4: SSE stream endpoint

**Files:**
- `StreamController.php` — `GET /stream`, returns `text/event-stream`
- `app/Services/Activity/SseStream.php` — replays from `Last-Event-ID` header; long-poll loop querying new rows through `ActivityQuery`; heartbeat comment every 30 s
- Middleware: Sanctum auth (token via query param `?token=` for EventSource, or cookie — document chosen approach)
- Rate limit: generous; one connection per user

**Check:** Feature test: insert activity row → stream receives `id`, `event`, `data` JSON; reconnect with `Last-Event-ID` replays missed rows only.

---

### Task 5: Notifications — schema, classes, delivery rules

**Files:**
- Migration: Laravel `notifications` table (`php artisan notifications:table`)
- Notification classes (database channel only): `TaskQueuedNotification`, `TaskOverdueNotification`, `TaskEscalatedNotification`, `TaskBlockedNotification`, `ApprovalWaitingNotification`, `SiteNotReadyNotification`, `CorrectiveActionAssignedNotification`, `PaymentQueriedNotification`, `JournalQueriedNotification`, `TimerNeedsReviewNotification`, `GroupedQueueNotification`
- `app/Services/Notifications/NotificationDispatcher.php` — volume rules from `10-notifications` §2:
  1. Mark department queue notifications read when any member claims the task
  2. One notification per event per user (idempotency key on `data`)
  3. Skip notifying the actor of their own action
  4. Group ≥2 queue arrivals within 60 s into one "N new tasks in {dept} queue"
  5. Escalation is a separate notification type with explicit wording
- Listeners wired to existing Laravel events + Plan 3/4 events

**Check:** Two users in Reception receive one grouped notification when three tasks land; first claim marks the other user's copy read.

---

### Task 6: Notifications API

**Files:**
- `NotificationController.php` — `index`, `markRead`, `markAllRead`
- `NotificationResource.php` — message, project, link, read_at, created_at; grouped by today/yesterday/earlier in frontend
- Unread count in `GET /auth/me` meta or dedicated `GET /notifications/unread-count`

**Check:** `POST /notifications/read-all` sets `read_at` on all unread; clicking a task notification link target resolves even if already claimed (frontend handles message).

---

### Task 7: Background jobs — overdue and escalation

**Files:**
- `app/Jobs/RecalculateOverdueTasks.php` — every 10 min; sets `is_overdue`, emits `task.overdue` activity + claimant notification once
- `app/Jobs/EscalateOverdue.php` — every 30 min; second-level supervisor notification per SLA
- Schedule in `routes/console.php` (alongside existing `OpenDailyJournal`)

**Check:** Task past deadline inside working hours → one overdue activity, one notification; second run does not duplicate.

---

### Task 8: Project read APIs

**Files:**
- `ProjectController.php` — `index`, `show`, `store`, `update`
- `ProjectWorkflowController.php` — `GET /projects/{id}/workflow` (stage strip + next action line)
- Section endpoints: `/projects/{id}/tasks`, `/payments`, `/hours`, `/samples`, `/site-visits`, `/activity`
- `ProjectPolicy.php` — row-level visibility
- Resources: `ProjectResource`, `ProjectListResource`, `ProjectWorkflowResource`, section-specific list resources
- `ProjectFilter.php` — stage, status, sales, blocked, overdue, search

**Check:** `GET /projects/{id}/workflow` returns two active stages when sample and site tasks are both open (A7); Delivery stage present but `configured: false`.

---

### Task 9: Control Room dashboard API

**Files:**
- `DashboardController.php` — `controlRoom()`
- `app/Services/Dashboard/ControlRoomService.php` — single query batch:
  - **KPIs:** active projects, blocked tasks, overdue tasks, awaiting approval, sites not ready, workshop timers active, on-site today (crew logs — end-of-day, labelled separately)
  - **Active projects:** cards sorted blocked → overdue → rest; each includes next-action line
  - **Needs attention:** blockers (by age), waiting approval, sites not ready with corrective actions
- Each KPI tile includes `filter_href` query params for linked list screens

**Check:** One HTTP call returns all three sections; zero counts are valid, not errors.

---

### Task 10: Workshop, Site, Samples dashboard APIs

**Files:**
- `ControlRoomService` siblings: `WorkshopDashboardService`, `SiteDashboardService`, `SamplesDashboardService`
- `DashboardController` — `workshop()`, `site()`, `samples()`
- Workshop: samples to make, in-progress with elapsed time, formulas to author, active timers, blocked, ready to hand back
- Site: active sites, awaiting inspection, not ready, re-inspection due, corrective actions by responsible party, today's crew logs + "not yet reported"
- Samples: columns by status per `00-screen-map.md` §8; attempt ≥4 flagged

**Check:** Feature tests with seeded Plan 3/4 data; workshop timer count matches `time_entries` where ended_at is null.

---

### Task 11: Global search API

**Files:**
- `SearchController.php` — `GET /search?q=`
- Search projects, samples, tasks, clients, site visits; grouped by type; visibility-filtered

**Check:** Query `SO9577` returns project + related samples/visits in one response.

---

### Task 12: Backend feature tests

**Files:**
- `tests/Feature/ActivityApiTest.php`
- `tests/Feature/SseStreamTest.php`
- `tests/Feature/NotificationTest.php`
- `tests/Feature/ProjectApiTest.php`
- `tests/Feature/DashboardTest.php`
- `tests/Feature/SearchTest.php`

**Check:** `php artisan test --filter="ActivityApi|SseStream|Notification|ProjectApi|Dashboard|Search"`

---

## Frontend tasks

### Task 13: Shared live-layer components

**Files:**
- `src/components/common/activity-feed-line.tsx` — dense one-line feed per `15-engineering-standards.md` §B2b; severity colour from tokens
- `src/components/common/status-chip.tsx` — task status + overdue + site-not-ready variants
- `src/components/common/workflow-strip.tsx` — horizontal stage map; two live stages; Delivery greyed
- `src/components/common/deadline-label.tsx` — "due in 3 hours" / "2 days late"
- `src/components/projects/project-card.tsx` — control room + list card with next-action line
- Read `design-system/DESIGN-SYSTEM.md` before styling

**Check:** Storybook-style manual check in dev: 30 feed lines fit without card chrome; RTL mirrors correctly.

---

### Task 14: Activity store, SSE hook, polling fallback

**Files:**
- `src/hooks/use-activity-stream.ts` — EventSource to `/api/v1/stream`; `Last-Event-ID`; reconnect banner state
- `src/lib/activity-store.ts` — normalised events by id; merge SSE + poll + initial fetch
- Poll fallback: `GET /activity?since=` every 15 s when SSE errors or `stream disconnected`
- `src/services/activityService.ts`

**Check:** Kill SSE mid-session → banner "Reconnecting…"; poll keeps feed current; no duplicate rows.

---

### Task 15: Notification centre

**Files:**
- `src/components/common/notification-bell.tsx` — unread badge
- `src/components/common/notification-panel.tsx` — grouped list, mark one/all read
- `src/services/notificationService.ts`
- Wire into header/sidebar shell

**Check:** Claim task from another tab → badge decrements for department colleagues; link opens task or "already claimed" message.

---

### Task 16: Control Room page

**Route:** `/` (replace template dashboard; redirect users without `project.view_all` to `/my-tasks`)
**Spec:** `specs/09-screens/01-control-room.md`

**Files:**
- `src/app/(with-layouts)/(dashboard)/(home)/page.tsx` — rewrite
- `src/components/control-room/` — kpi-row, live-feed, active-projects, needs-attention
- `src/services/dashboardService.ts` — `getControlRoom()`
- Initial load: one `GET /dashboard/control-room`; feed prepends SSE events; KPIs refresh every 60 s or on relevant SSE types

**Check:** Desktop three columns; mobile stack: alerts → feed → projects; skeleton loading, never full-screen spinner.

---

### Task 17: Projects list page

**Route:** `/projects`
**Spec:** `00-screen-map.md` §6

**Files:**
- `src/app/(with-layouts)/projects/page.tsx`
- `src/components/projects/projects-list.tsx` — table desktop, cards mobile; filters + search

**Check:** Filter "blocked" matches API; empty state copy present.

---

### Task 18: Project Detail page

**Route:** `/projects/[reference]`
**Spec:** `specs/09-screens/03-project-detail.md`

**Files:**
- `src/app/(with-layouts)/projects/[reference]/page.tsx`
- Sections as tabs (desktop) / accordion (mobile): Tasks, People & hours, Samples, Site, Payments, Activity, Files
- Header + `WorkflowStrip` + prominent **Next:** line
- Overflow actions menu — permission-gated (`sample.create`, `payment.confirm`, etc.)
- Activity tab reuses `ActivityFeedLine` scoped to project

**Check:** Lead project shows strip with Lead only + permitted first-action prompt; cancelled/completed banners; no cost/material/AI tabs.

---

### Task 19: Workshop, Site, Samples dashboard pages

**Routes:** `/workshop`, `/site`, `/samples`
**Spec:** `00-screen-map.md` §8, §12, §14

**Files:**
- Three page components under `src/app/(with-layouts)/`
- One service call each; permission-gated nav entries
- Site dashboard: never label crew-log counts as "live"; workshop timers labelled "live"

**Check:** Workshop shows elapsed timers updating; site shows "not yet reported" for missing crew logs.

---

### Task 20: Navigation, i18n, and integration

**Files:**
- Update sidebar/bottom nav per `00-screen-map.md` § Navigation — permission-filtered items
- `messages/en.json` + `messages/ar.json` — control room, notifications, project detail strings
- Invalidate React Query keys on task mutations so dashboards stay fresh
- Optional: `GET /search` wired to header search field

**Check:** Workshop supervisor sees four nav items, not twelve; Arabic RTL layout on Control Room and Project Detail.

---

## Definition of done

- [ ] `composer qa` passes (API)
- [ ] Activity + SSE + notification + dashboard + project feature tests green
- [ ] Frontend lint passes
- [ ] Demo: login as management → Control Room loads in one request → complete a task elsewhere → feed updates via SSE within seconds → notification appears for department → open Project Detail → workflow strip and **Next:** line match the queue
- [ ] Demo: SSE blocked → polling fallback keeps feed current without duplicates
- [ ] No Phase 2 scope slipped in (email, cost tab, delivery workflows, reports)

---

## Suggested execution order

1. Backend Tasks 1–4 (activity + SSE) — unblocks feed UI
2. Backend Tasks 5–7 (notifications + jobs)
3. Backend Tasks 8–11 (projects + dashboards + search)
4. Backend Task 12 (tests)
5. Frontend Tasks 13–15 (shared + stream + notifications)
6. Frontend Tasks 16–20 (screens + nav)

Plans 3 and 4 should land before Task 2's sample/site listeners and Tasks 10/19 dashboards are tested with real data. Task 2 can stub listeners behind events that Plans 3/4 will dispatch.
