# Screen — Working Calendar and Holidays

**Route:** `/admin/calendar`
**Who:** `settings.manage` and `holiday.manage`
**Data:** `GET /admin/settings`, `GET /admin/holidays`

`[CONFIRMED]` A14, A14b, A14c

---

## Why this screen matters more than it looks

Every deadline in the system is computed through this calendar. A task with a
4-hour SLA created at 15:00 on a Thursday is due at 11:00 on Saturday, not at
19:00 on Thursday. `06-task-and-time-tracking.md` §6

So an admin editing this screen is editing every open deadline in the company.
The screen has to say so, plainly, before they save.

---

## Part 1 — Working hours

| Setting | Value | Notes |
|---|---|---|
| Shift start | `09:00` | `[CONFIRMED]` A14b |
| Shift end | `17:00` | `[CONFIRMED]` A14b |
| Weekend days | Friday | `[CONFIRMED]` A14. Multi-select, so a second day can be added without a code change |
| Timezone | `Africa/Cairo` | Read-only |

An 09:00–17:00 shift is **8 working hours per day**. This is the unit every SLA
is expressed in, so the screen states it: *"One working day = 8 hours."*

Validation: shift end must be after shift start. An overnight shift is not
supported in Phase 1 — if the company ever runs one, that is a change to
`WorkingCalendar`, not a setting.

---

## Part 2 — Holidays

A list, newest first, grouped by year.

| Column | |
|---|---|
| Date | |
| Name | Arabic and English, both editable. `15-engineering-standards.md` §A4 |
| Type | `public` or `company` `[PROPOSED]` |
| Recurring | For fixed-date holidays that repeat every year `[PROPOSED]` |
| Added by | Read-only |

**Actions:** add, edit, delete. Each writes an audit row.

**Type** exists so a company shutdown day can be told apart from a national
holiday in a report later. `[PROPOSED]` — remove it if the client does not care.

**Recurring** applies to fixed-date holidays only. Islamic holidays move against
the Gregorian calendar every year and must be entered each year. The screen says
this next to the checkbox, because assuming otherwise produces silently wrong
deadlines twelve months later.

---

## Part 3 — The consequence of a change

This is the part that must not be skipped.

When an admin adds, moves or deletes a holiday, or changes the shift hours, every
open task whose deadline was computed through the affected period is now wrong.

**Behaviour `[PROPOSED]`:**

1. On save, the system counts the affected open tasks **before** committing.
2. It shows a confirmation naming the number:

   > Adding **23 September** as a holiday will change the deadline of
   > **14 open tasks**. Their new deadlines will be recalculated.
   > Tasks already completed are not affected.

3. On confirm, a `RecalculateDeadlines` job recomputes `due_at` for every open
   task from its original SLA and its `ready_at`, through the new calendar.
4. Completed and cancelled tasks are never touched. History stays as it was.
5. A task whose deadline was manually overridden with `task.override_deadline`
   is **not** recalculated. A human decision beats a calculated one.
6. One audit row for the calendar change, and one activity event:
   *"Calendar changed by Admin — 14 task deadlines recalculated."*

Without step 3, the calendar and the deadlines drift apart and every overdue
figure in the system becomes untrustworthy. Without step 5, an admin adding a
holiday silently undoes a manager's decision.

---

## Part 4 — Preview

A small month view showing weekends and holidays shaded, so the admin can see
what they have built rather than reading a list of dates. Read-only. Clicking a
day with a holiday scrolls to its row.

---

## States

| State | Behaviour |
|---|---|
| Loading | Skeleton table |
| Empty | "No holidays added yet. Deadlines currently only skip Fridays." — the consequence, not just the emptiness |
| Saving | The confirmation dialog with the affected-task count. Never save silently |
| No permission | 404 |

---

## API

| Method | Path | Permission |
|---|---|---|
| GET | `/admin/settings` | `settings.manage` |
| PATCH | `/admin/settings` | `settings.manage` |
| GET | `/admin/holidays` | `holiday.manage` |
| POST | `/admin/holidays` | `holiday.manage` |
| PATCH | `/admin/holidays/{id}` | `holiday.manage` |
| DELETE | `/admin/holidays/{id}` | `holiday.manage` |
| POST | `/admin/calendar/impact` | Returns the affected open-task count for a proposed change, without saving |

`/admin/calendar/impact` is what powers the confirmation dialog. It is a dry run:
same calculation, nothing written.

---

## Tests

1. A 4-hour SLA starting Thursday 15:00 lands Saturday 11:00, given a Friday
   weekend.
2. The same, with Saturday added as a holiday, lands Sunday 11:00.
3. Adding a holiday recalculates open tasks and leaves completed tasks alone.
4. A manually overridden deadline survives a calendar change.
5. The impact endpoint returns the same count that the recalculation then
   changes.
6. Shift end before shift start is rejected.
7. A recurring holiday applies in the following year; a non-recurring one does
   not.
