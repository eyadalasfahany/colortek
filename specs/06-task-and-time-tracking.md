# 06 — Task Lifecycle and Time Tracking

---

## 1. Task statuses `[PROPOSED]` B4

| Status | Meaning | Claimable |
|---|---|---|
| `pending` | Exists, but held because something is wrong elsewhere (usually a site block) | no |
| `waiting` | Exists, nothing wrong, waiting for a predecessor or an outside event | no |
| `ready` | In the department queue. Anyone in that department can take it | **yes** |
| `claimed` | Someone took it but has not started | — |
| `in_progress` | Being worked on. Timer running if the task type uses one | — |
| `paused` | Started, temporarily stopped. Timer stopped | — |
| `blocked` | A problem is stopping it. Category and reason required | — |
| `completed` | Finished. Successors created | — |
| `cancelled` | Abandoned. Transitions never fire | — |

`is_overdue` is a **flag**, not a status. `[PROPOSED]` B3 — a task can be
`in_progress` and overdue at once, which is the case managers most need to see.

### 1.1 Allowed transitions

```
pending    → ready | cancelled
waiting    → ready | cancelled
ready      → claimed | pending | cancelled
claimed    → in_progress | ready (release) | blocked | cancelled
in_progress→ paused | blocked | completed | cancelled
paused     → in_progress | blocked | cancelled
blocked    → in_progress | paused | ready (released) | cancelled
completed  → (terminal)
cancelled  → (terminal)
```

Anything not on this list is rejected by `TaskService`, not only by the UI.

### 1.2 Why `claimed` is separate from `in_progress`

Because taking a task and starting work are different moments. A workshop
supervisor claims three sample tasks in the morning so nobody else takes them,
then starts them one at a time. If claiming started the timer, all three would
record eight hours.

For office tasks with `requires_timer = false`, the frontend collapses Claim and
Start into one button. The two states still exist underneath.

---

## 2. Claiming `[CONFIRMED]` A10

- A task in `ready` belongs to a **department**, not a person.
- Any user in that department with `task.claim` may take it.
- Claiming is atomic: `UPDATE tasks SET claimed_by_user_id = ? WHERE id = ? AND
  claimed_by_user_id IS NULL`. If zero rows are affected, someone else got it
  first and the user is told, plainly, that the task was taken.
- A user may release a task back to the queue. Their recorded time stays.
- A user with `task.reassign` may force-release someone else's claim — for
  absences. This is audited.

There is no "assign to person" feature in Phase 1. If it turns out the company
needs one, that is a change, not a setting.

---

## 3. Blocking `[CONFIRMED]` A16

To block a task the user must give:

| Field | Required |
|---|---|
| Category | always — one of the four seeded categories |
| Written reason | always |
| Expected resolution date | only if the category demands it |
| Photo or file | optional, encouraged for site blockers |

On block:
- The timer stops. Blocked time accumulates into `blocked_seconds` separately
  from working time, so "this took 3 days" and "we worked on it for 4 hours" are
  both true and both visible.
- A `blocker` severity activity event is written.
- If the category has a `notifies_department_id`, that department is notified.
  "Missing material" reaching the warehouse is the entire point of the category.
- The project shows a blocker badge.

Unblocking requires a resolution note.

---

## 4. Completing

Validation before a task can complete:

1. The user holds the claim.
2. Every key in `required_fields` has a non-empty value.
3. Every type in `required_attachment_types` has at least one file.
4. Task-type business rules pass — see the individual workflow specs.
5. Any running timer is stopped first.

If any check fails, nothing is saved and the reason is shown next to the field
that caused it. Never a generic "validation failed".

---

## 5. Time tracking

Two different mechanisms, because the client works two different ways.
`[CONFIRMED]` A12, A13

### 5.1 Workshop — live timers `[CONFIRMED]` A12

The supervisor runs the timer. Individual workers do not log in. `[CONFIRMED]` A11

`time_entries` rows are created on start and closed on stop.

Controls: **Start**, **Pause**, **Resume**, **Complete**.

Rules:

- One running timer per user across the whole system. `[PROPOSED]` B10.
  Starting a second one pauses the first and records that it did so, rather than
  silently double-counting.
- A supervisor may attribute the time to a named `employee_id`, so the hours land
  on the worker who did the work rather than the supervisor who pressed the
  button. If no employee is named, the time belongs to the supervisor.
- Several employees can be on the same task at once: several open
  `time_entries` rows on one task, each with a different `employee_id`.
- `tasks.active_seconds` is a cached sum, recomputed whenever an entry closes.
  Never trust it as the source; `time_entries` is the source.

**Forgotten timers.** `CloseStaleTimers` runs hourly. Any entry still open past
the end of the shift is closed at the shift end time, marked
`source = auto_closed` and `needs_review = true`. The supervisor sees it the next
morning with a prompt to confirm or correct the hours. Without this job, one
forgotten timer produces a 60-hour sample task and every hours report becomes
untrustworthy.

**Corrections.** A user with `time.correct` can edit an entry. The edit requires
a note and writes an audit row with the old and new values. The original value is
never overwritten in the audit trail.

### 5.2 Site — end-of-day crew log `[CONFIRMED]` A13

No timers on site. At the end of the day the site supervisor submits one record
per project:

| Field | Notes |
|---|---|
| Date | one per project per day |
| Task | which execution task the day's work belongs to |
| Workers | a list of employees, each with hours and an optional role note |
| Work done | free text |
| Issues | free text, optional |
| Weather note | optional |
| Photos | attachments |

Submitting is a deliberate action. A draft can be edited; a submitted log needs
`time.correct` to change, and the change is audited.

**"People working now" on the dashboard.** Live timers give a genuinely live
number for the workshop. The site cannot, because the log arrives at the end of
the day. So the site figure is labelled **"on site today"** and comes from
today's submitted logs, plus a separate count of "not yet reported" for projects
with an active execution task and no log. Showing an end-of-day figure as if it
were live would be a lie, and the site supervisors would stop trusting the
dashboard within a week.

---

## 6. Working calendar `[CONFIRMED]` A14

Settings: `work_start` = `09:00`, `work_end` = `17:00` `[CONFIRMED]` A14b,
`weekend_days` = `["friday"]` `[CONFIRMED]` A14, plus the `holidays` table,
which an admin maintains through a screen `[CONFIRMED]` A14c.

One working day is **8 hours**. Every SLA is expressed in that unit.
`16-sla-defaults.md`

`WorkingCalendar` provides:

- `isWorkingTime(Carbon $t): bool`
- `addWorkingMinutes(Carbon $from, int $minutes): Carbon`
- `workingMinutesBetween(Carbon $a, Carbon $b): int`

Used for deadlines, overdue, SLA reporting and blocked-duration reporting.

Edge cases the unit tests must cover:
- A deadline starting after the end of the shift begins counting the next
  working morning.
- A deadline crossing a Friday.
- A deadline crossing a Friday **and** a public holiday on the Saturday.
- Adding a holiday must recalculate the deadline of every open task, and must
  leave completed tasks and manually overridden deadlines alone.
  `09-screens/06-admin-calendar-and-holidays.md` §3
- An overnight shift is **not supported** in Phase 1. `work_end` must be after
  `work_start`. If the company ever runs one, that is a change to
  `WorkingCalendar`, not a setting

---

## 7. What a task screen shows

The screen a worker sees decides whether the system is used or ignored.

Top: project, client, task title, status, priority, deadline with the time
remaining in words, overdue badge if set.

Then, in order:

1. **What you need to do** — the instructions from the task definition.
2. **What the last person did** — the previous task's outputs and files, read
   directly, so nothing has to be asked for. This section is the whole product
   promise made visible.
3. **The form** — rendered from `form_schema`.
4. **Files** — required types listed explicitly, with a clear mark showing which
   are still missing.
5. **The buttons** — Claim / Start / Pause / Block / Complete. One primary
   action, obvious, large enough to press with a work glove.
6. **Activity** — the status timeline and comments.

On mobile, sections 1, 2 and 5 are visible without scrolling. `[CONFIRMED]` A32
means every one of these has an Arabic mirror layout, not a translated
left-to-right layout.
