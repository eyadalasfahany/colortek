# Screen — Task Detail

**Route:** `/tasks/{id}`
**Who:** everyone
**Data:** `GET /tasks/{id}`

The screen where the work actually happens. If this screen is confusing, the
system fails, however good the dashboards are.

Design for a workshop supervisor holding a phone with one hand.

---

## Layout, top to bottom

### 1. Header
Project reference and name, client, task title, status chip, priority if above
normal, deadline in words with an overdue badge if set.

### 2. What you need to do
The instructions from the task definition, in the user's language.

### 3. What the last person did
**The most important section on the screen, and the one that justifies the whole
project.**

It shows the previous task's outputs and files, read directly from the record,
so nothing has to be asked for on WhatsApp.

For a Reception payment review task, this section shows: the salesperson's name,
the amount, the method, the payment date, their notes, and the proof image inline
— large enough to read the bank reference without downloading it.

For a workshop sample task: the colour, texture, client reference, size, the
finish requirement, the client's reference photo, and — if this sample came from
a modification — the parent sample's reference and the exact reason the client
rejected it. `[PROPOSED]` B2

Nothing in this section is editable. It is what arrived with the task.

### 4. The form
Rendered from the task definition's `form_schema`.
`03-data-model.md` §5.1. Field types, labels and required marks all come from the
schema, so a new task type needs no frontend work.

### 5. Files
Required attachment types are listed explicitly, each with a clear state:

```
Payment proof      ✓ transfer.jpg          [replace]
Signed form        ✗ required              [ upload ]
```

Never let someone press Complete and only then discover a missing file. Show
what is missing before they try.

Upload happens immediately and returns an id. Completion references the ids.
`08-api-contract.md` §12 — a site engineer on weak data must not lose a filled
form because one photo failed.

### 6. Actions
One primary button, changing with status:

| Status | Primary | Secondary |
|---|---|---|
| `ready` | **Claim** | — |
| `claimed` | **Start** | Release, Block |
| `in_progress` | **Complete** | Pause, Block, Add comment |
| `paused` | **Resume** | Block |
| `blocked` | **Unblock** | Add comment |
| `waiting` / `pending` | none — the reason is shown instead | Add comment |
| `completed` | none | — |

For office tasks (`requires_timer = false`) Claim and Start are one button. The
two states still exist underneath. `06-task-and-time-tracking.md` §1.2

When the timer is running, elapsed time is large and obvious at the top. A
supervisor must be able to see at a glance that they left a timer on.

### 7. Activity
The status timeline (who claimed, started, paused, blocked, completed, and when)
and the comment thread.

---

## Completing a task

On Complete the frontend validates required fields and files locally first, then
posts. On success the response names what was created, and the screen shows it
plainly:

> **Done.** Reception now has *"Review payment"*, due today at 4pm.

Then it returns to My Tasks.

That one sentence is the entire promise of the product made visible. Do not
replace it with a generic toast.

If the server rejects it, the message names the field or the file and scrolls to
it. Never a generic error.

---

## Blocking

Blocking opens a small form, not a free-text box:

| Field | |
|---|---|
| Category | one of the four seeded categories. `[CONFIRMED]` A16 |
| What is wrong | required, free text |
| Expected resolution date | shown only when the category requires it |
| Photo | optional |

On submit, the timer stops and the relevant department is notified — "missing
material" reaching the warehouse is the point of the category, not decoration.
`06-task-and-time-tracking.md` §3

---

## Site-blocked tasks

A task held because the site is not ready shows a clear panel instead of the
action button:

> **Held — the site is not ready.**
> Site visit SO9577-SV1, 18 Sep: other contractors still on site.
> 2 corrective actions open.

A user with `site.override_block` also sees an override button. Pressing it
requires a typed reason and warns that the override will be recorded and shown to
management. `[CONFIRMED]` A31

---

## States

| State | Behaviour |
|---|---|
| Loading | Skeleton, with the header filled from the list if available |
| Claimed by someone else | Read-only, showing who has it. Reassign available with permission |
| Already completed | Read-only, full history |
| Offline | The form keeps its local draft. Completing while offline is refused with a plain message, not a silent failure |
| No permission | 404, never a "you may not see this" that leaks existence |
