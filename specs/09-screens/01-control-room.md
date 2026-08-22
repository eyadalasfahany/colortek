# Screen — Control Room

**Route:** `/`
**Who:** management, admin, reception (`project.view_all`)
**Data:** `GET /dashboard/control-room`, plus the SSE stream

The screen a manager opens in the morning and leaves open all day. It must answer
the client's core question without a single click: *what is happening right now,
and what is stuck?*

---

## Layout

Desktop, three columns. Mobile, the same three sections stacked in this order:
alerts first, then the feed, then the projects. On a phone a manager wants the
problems, not the overview.

```
┌──────────────────────────────────────────────────────────────┐
│  KPI row                                                     │
├──────────────┬─────────────────────────┬─────────────────────┤
│ Live feed    │  Active projects        │  Needs attention    │
│ (stream)     │  (cards)                │  (alerts)           │
└──────────────┴─────────────────────────┴─────────────────────┘
```

---

## KPI row

Seven tiles. Each is a filter link, not a dead number.

| Tile | Source | Links to |
|---|---|---|
| Active projects | projects not completed or cancelled | Projects filtered active |
| Blocked tasks | tasks in `blocked` | Tasks filtered blocked |
| Overdue tasks | `is_overdue = true`, not completed | Tasks filtered overdue |
| Awaiting approval | open `manager_approve_sample` tasks | Samples awaiting approval |
| Sites not ready | latest visit per project = `not_ready` | Site dashboard |
| Working now (workshop) | open `time_entries` | Workshop dashboard |
| On site today | employees in today's submitted crew logs | Site dashboard |

The last two are deliberately labelled differently. Workshop is genuinely live;
site is end-of-day. Presenting them as one number would be dishonest and the site
supervisors would notice within a week. `06-task-and-time-tracking.md` §5.2

A tile showing zero is grey, not red. Red is reserved for things that are wrong.

---

## Live feed

The company activity stream, newest first, streaming in.

One dense line per event:

```
09:41  Sales · Ahmed        SO9577   Payment confirmed, EGP 50,000        →
09:41  System              SO9577   Reception now has "Review payment"    →
10:20  Management · Sara   SO9581   Sample SO9581-S2 approved             →
12:01  Site · Mostafa      SO9577   Site marked NOT READY                 →
12:02  System              SO9577   3 corrective actions created          →
```

Rules:

- System-generated lines are visually quieter than human actions, but never
  hidden. The automatic handover is the product; people need to see it working.
- Severity gives the colour: info, success, warning, blocker, approval.
- Every line links to its record.
- Filter by project, department and severity.
- Only events this user may see. Filtered in the query, not in the browser.
  `04-permissions-and-roles.md` §4

This is an operations log, not a social feed. Dense lines, no avatars, no cards,
no infinite whitespace. A manager should be able to read thirty events without
scrolling.

---

## Active projects

Cards, sorted by attention needed: blocked first, then overdue, then the rest.

Each card:

```
SO9577   Omega — Mahmoud Eslily            [ SITE NOT READY ]
New Giza · Sales: Ahmed
Stage: Site  ·  Sample: approved (2 attempts)
Open tasks: 4   Blocked: 1   On site today: 6

Next:  Reinspection — Site team
```

The **Next** line is the whole product in one row. It names the single next
expected action and the department that owns it. `[CONFIRMED]` — this is the
client's stated final design principle.

Cost is absent by design. Phase 2. `[CONFIRMED]` A2

---

## Needs attention

Three grouped lists, most urgent first:

1. **Blockers** — every blocked task, with category, how long it has been
   blocked, project and who blocked it. Sorted by age. A blocker that is four
   days old sits at the top.
2. **Waiting for approval** — sample approvals, with age. These are the cheapest
   delays in the company to fix and the easiest to forget.
3. **Sites not ready** — with the open corrective actions and, importantly, who
   is responsible for each. Most will be the client's responsibility, and showing
   that plainly protects the site team.

Each entry is one click from the record.

---

## Behaviour

- Loads in one request. Ten requests to paint a dashboard is what makes an
  operations screen feel slow.
- The feed updates over SSE; the KPI tiles refresh every 60 seconds or when a
  relevant event arrives.
- On reconnect the feed replays missed events using `Last-Event-ID`. It must
  never silently lose events. `02-architecture.md` §4
- Skeleton loading, never a full-screen spinner.

---

## States

| State | Behaviour |
|---|---|
| Loading | Skeleton tiles, skeleton feed lines |
| Empty | "No active projects yet." with a link to create one, if permitted |
| Stream disconnected | A quiet banner: "Reconnecting…". Falls back to polling automatically. Never a blocking dialog |
| No permission | The user is redirected to My Tasks, which everyone has |
