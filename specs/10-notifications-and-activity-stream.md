# 10 — Notifications and the Activity Stream

Two different things that are easy to confuse.

| | Activity stream | Notifications |
|---|---|---|
| Answers | "What is happening in the company?" | "What do *I* need to do?" |
| Audience | Everyone who may see the project | One specific person or department |
| Read state | None | Read / unread |
| Volume | High | Must stay low |
| Storage | `activity_events` | Laravel `notifications` |

Phase 1 delivery is **in-app only**. `[CONFIRMED]` A33 — no email, no WhatsApp.

---

## 1. Activity stream

Written by event listeners, never inline in a service. `02-architecture.md` §2.

Every row is immutable. Never edited, never deleted.

### Messages are rendered at write time

`message_en` and `message_ar` are stored as finished text, not as a template
resolved later. `03-data-model.md` §10

Why: if a project is renamed or a user is deactivated, a template rendered later
would quietly rewrite history — "Ahmed confirmed payment" would become
"Deleted user confirmed payment". The feed is a record of what happened; it must
read the same in a year.

Both languages are written at the same moment, because we cannot know who will
read it. `[CONFIRMED]` A32

### Severity

| Severity | Used for | Look |
|---|---|---|
| `info` | routine progress | quiet |
| `success` | something completed | quiet green mark |
| `approval` | an approval is needed or was given | distinct |
| `warning` | a repeated sample attempt, an override, a query sent back | amber |
| `blocker` | a task blocked, a site declared not ready | red, loudest |

The feed must not look like a social feed. `[CONFIRMED]` from the vision document
section 43. One dense line per event.

### Event types

| Type | Severity | Written when |
|---|---|---|
| `task.created` | info | The engine creates a task. This is the automatic handover; showing it is the point |
| `task.claimed` | info | |
| `task.started` | info | |
| `task.blocked` | blocker | |
| `task.unblocked` | success | |
| `task.completed` | success | |
| `task.overdue` | warning | First time a task passes its deadline |
| `task.reassigned` | warning | |
| `payment.confirmed` | success | |
| `payment.queried` | warning | Reception sent it back |
| `journal.submitted` | success | |
| `journal.reopened` | warning | Always, even when system-driven |
| `sample.requested` | info | |
| `sample.approved_by_manager` | approval | |
| `sample.rejected_by_manager` | warning | |
| `sample.made` | success | |
| `sample.approved_by_client` | success | |
| `sample.rejected_by_client` | warning | |
| `sample.modification_requested` | warning | A repeat attempt costs money |
| `sample.repeat_attempt_threshold` | warning | Attempt 4 or more |
| `formula.authored` | info | |
| `formula.registered` | success | |
| `formula.corrected` | warning | |
| `site.visit_submitted` | info | |
| `site.ready` | success | |
| `site.not_ready` | blocker | |
| `site.corrective_action_created` | warning | |
| `site.override_used` | warning | Management must see every override |
| `project.stage_changed` | info | |
| `project.completed` | success | |

### Visibility

Filtered in the query, never in the browser:

1. Project-level visibility from `04-permissions-and-roles.md` §4.
2. `activity_events.visible_to_permission`, if set.

Nothing in Phase 1 sets `visible_to_permission`, because there is no cost data
yet. It exists so that Phase 2 cost events cannot reach a worker's screen by
accident. `[CONFIRMED]` A2

---

## 2. Notifications

A notification exists to make someone act. If it does not, it should not exist.

### What produces one

| Event | Who is notified |
|---|---|
| A task lands in a queue | Every member of that department |
| A task you claimed becomes overdue | The claimant |
| A task is overdue past its escalation time | Every supervisor of that department |
| A task is blocked with a category that names a department | That department |
| An approval is waiting | Everyone holding `sample.approve_manager` |
| A site is declared not ready | The project's sales user and responsible user |
| A corrective action is assigned to you | The assignee |
| Your payment was queried by Reception | The salesperson who confirmed it |
| The journal was queried by Accounting | Reception |
| A timer was auto-closed and needs review | The supervisor who started it |

### Rules that keep the volume survivable

1. **A department task notification stops as soon as one person claims it.** The
   others are marked read automatically. Nobody should tidy up notifications for
   work someone else took.
2. **No duplicates.** One notification per event per user. An overdue task
   notifies once, not every ten minutes when the job runs.
3. **No notification for your own action.** You know what you just did.
4. **Grouping.** Five tasks landing in the same queue within a minute become one
   notification: "5 new tasks in the Workshop queue."
5. **Escalation is a different notification**, not a repeat of the first, and it
   says plainly that it is an escalation.

A system that notifies too much gets muted, and a muted system does not deliver
work. Volume control is a feature, not polish.

### The notification centre

A badge with the unread count. The list groups by today, yesterday, earlier.
Each entry: what happened, which project, how long ago, and a link. Mark one
read, mark all read.

Clicking a notification about a queued task opens the task. If someone else has
already claimed it, the screen says so plainly rather than showing an
unexplainable read-only page.

---

## 3. Phase 2

Email and WhatsApp are deferred. `[CONFIRMED]` A33.

To keep that cheap later, every notification is written as a Laravel
Notification class with the `database` channel only. Adding `mail` or a WhatsApp
channel later is a change to the channel list and a template, not a rewrite.
Per-user channel preferences and quiet hours also belong to that phase, and are
recorded in `14-phase-2-backlog.md`.
