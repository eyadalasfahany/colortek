# 09 — Screen Map

Phase 1 screens. Each entry gives the purpose, who sees it, the data it needs,
its states and its actions. The four hardest screens have their own file.

**Build from the components that already exist.** `colortek-frontend` ships
TailGrids core — 42 primitives plus a header and sidebar shell. Reuse before
creating; the full inventory and the nine components that still need building are
in `15-engineering-standards.md` §B2b.

Design tokens come from `design-system/DESIGN-SYSTEM.md`. Do not invent colours
or spacing. Every screen must work in Arabic RTL and English LTR.
`[CONFIRMED]` A32

Every list screen must define four states: **loading**, **empty**, **error**, and
**no permission**. An empty queue should say "Nothing waiting for your
department" — not show a blank box.

---

## Navigation `[PROPOSED]`

Adapts to permissions. A workshop supervisor sees four items, not twelve.

| Item | Shown when |
|---|---|
| Control Room | `project.view_all` |
| My Tasks | always |
| Queue | `task.view_own_queue` |
| Projects | `project.view` |
| Samples | `sample.view` |
| Site | `site.view` |
| Workshop | member of workshop or tinting |
| Journal | `journal.view` |
| People & Hours | `time.view_all` |
| Admin | `settings.manage` |
| Access (roles & users) | super admin only — hidden, not disabled, for everyone else |

On mobile the navigation collapses to a bottom bar with at most four items:
My Tasks, Queue, Projects, More.

---

## 1. Login
Who: everyone. Email + password, language switch (ar/en) **before** login, so a
worker who cannot read English can still get in. Errors are plain: "Wrong email
or password." Never reveal whether the email exists.

## 2. Control Room — `01-control-room.md`
Who: management, admin, reception.

## 3. My Tasks
Who: everyone.
Data: `GET /tasks?scope=my`.
Grouped: Overdue, Today, Blocked, Everything else. Each card shows project,
client, task title, deadline in words ("due in 3 hours", "2 days late"), status,
and the primary button. Tapping opens the task.
Empty state: "You have no tasks. Check the Queue for work waiting for your
department."

## 4. Queue
Who: anyone with `task.view_own_queue`.
Data: `GET /tasks?scope=queue`.
The department inbox. Same card, but the button is **Claim**. `[CONFIRMED]` A10
A claim that loses the race shows "Ahmed took this task" and the card disappears.
Filter by project, priority, overdue.

## 5. Task Detail — `02-task-detail.md`
Who: everyone.

## 6. Projects list
Who: `project.view`.
Table on desktop, cards on mobile: reference, name, client, stage, open tasks,
blockers, people on it today, next expected action. Filters: stage, status,
salesperson, blocked, overdue. Search by reference, name or client.

## 7. Project Detail — `03-project-detail.md`
Who: `project.view`.

## 8. Samples dashboard
Who: `sample.view`.
Columns by status: New requests · Awaiting manager approval · In workshop ·
Awaiting formula registration · Awaiting client decision · Approved · Rejected.
Each card: sample reference, client, project or a clear "Pre-sale" marker,
colour, texture, attempt number, current owner department, days in this stage.
Cards on their fourth attempt or older are marked. `[PROPOSED]` workflow 03 §4

## 9. Sample Detail — `04-sample-detail.md`
Who: `sample.view`.

## 10. Formula registration
Who: `formula.register`.
Reached from a task. Shows the sample and its photo, the authored text and the
scanned tinting sheet side by side, the author's name and date, and the previous
version if this is a repeat attempt. Reception confirms it matches, or types a
correction. The original text stays visible; a correction is added beside it,
never over it. See workflow 04.

## 11. Site Visit Form — `05-site-visit-form.md`
Who: `site.visit_create`. The most important mobile screen in the system.

## 12. Site dashboard
Who: `site.view`.
Active sites, awaiting inspection, not ready, re-inspection due, open corrective
actions grouped by responsible party, and today's crew logs with a clear
"not yet reported" list for projects working today with no log yet.
See `06-task-and-time-tracking.md` §5.2 — never present an end-of-day figure as
live.

## 13. Crew log
Who: `time.crew_log_submit`. Mobile first.
Date, project, task, then a worker list built by picking employees and typing
hours. Big touch targets. Work done, issues, photos. Save as draft, then Submit.
A submitted log is locked.

## 14. Workshop dashboard
Who: workshop and tinting members.
Samples to make, samples in progress with live elapsed time, formulas to author,
who is working now and on what, hours today, blocked tasks, and what is ready to
hand back to Reception.

## 15. Journal
Who: `journal.view`.
Today's journal with every reviewed payment: project, client, amount, method, a
link to each proof, and the running total. Reception submits; Accounting
processes. A submitted journal is read-only and says so. See workflow 01.

## 16. Activity and notifications
Who: everyone, filtered by visibility.
The live feed as an operations log, not a social feed: one dense line per event
with time, actor, department, project, what happened, and a severity colour.
Filter by project, department, severity. Blockers and approvals are visually
distinct from routine events. `[CONFIRMED]` A33 — in-app only.

## 17. People and hours
Who: `time.view_all`.
Hours by project, by department, by employee, for a date range. Workshop hours
come from timers; site hours from crew logs. The two sources are labelled — they
are not the same kind of number and must not be silently added into one column
without saying so.

## 18. Admin — Calendar and Holidays — `06-admin-calendar-and-holidays.md`
Who: `settings.manage`, `holiday.manage`. `[CONFIRMED]` A14b, A14c

## 19. Admin — Roles, Permissions and Users — `07-admin-roles-and-permissions.md`
Who: super admin. `[CONFIRMED]` A4b

## 20. Admin — everything else
Who: `settings.manage`.
- Workflow templates: view, edit a draft, publish a version. Publishing creates
  version N+1 and never touches running work. `[PROPOSED]` B5
- Settings: humidity maximum, repeat-attempt threshold,
  block-all-on-site-not-ready, default SLA per task type (`16-sla-defaults.md`)
- Site checklist items: the five items from the paper form, editable
- Stalled instances, unclaimed queues and failed jobs — `02-architecture.md` §9
- Audit log viewer with filters

## 21. Global search
One field. Returns projects, samples, formulas, tasks, clients and site visits,
grouped by type, each with enough context to choose. Searching `SO9577` finds the
project, its samples, its formulas and its site visits. Searching a colour name
finds samples.

---

## Cross-cutting rules

**One primary action per screen.** Workers use this with dirty hands on a phone.
If two buttons look equally important, the screen is wrong.

**Deadlines in words.** "Due in 3 hours", "2 days late" — never a raw timestamp
alone. The exact time is in the tooltip.

**Blockers are loud.** A blocked task or a not-ready site is visible from the
project card, the queue, the dashboard and the feed. The client's main complaint
is not knowing what is stuck.

**Never show a spinner over a whole screen.** Show the layout with skeletons so
people know what is loading.

**Every screen names who is waiting.** The client's final design principle is
that nobody should have to ask who does the next step. Every project view answers
that question without a click.
