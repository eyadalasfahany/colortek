# Screen — Project Detail

**Route:** `/projects/{reference}`
**Who:** `project.view`, filtered by the row-level rules in `04` §4
**Data:** `GET /projects/{id}` plus the section endpoints in `08-api-contract.md` §4

The central operational view. One screen that answers every question about a
project without asking a person.

---

## Header

```
SO9577   Omega — Mahmoud Eslily                    [ SITE NOT READY ]
New Giza  ·  Client: Omega  ·  Sales: Ahmed  ·  Engineer: Mostafa
Stage: Site        Started 12 Aug        Target 30 Sep
```

Status chips are the loudest element: blocked, site not ready, overdue tasks.

---

## The live workflow strip

A horizontal stage map, the visual anchor of the screen.

```
Lead ─ Quotation ─ Payment ─ Sample ─ Site ─ Production ─ Execution ─ Delivery ─ Done
 ✓        ✓          ✓        ✓       ⛔       ·            ·           ·         ·
                                    not
                                   ready
```

Rules:

- Completed stages are ticked. The current stage or stages are highlighted.
- **Sample and Site can both be active.** `[CONFIRMED]` A7 — the strip must be
  able to show two live stages at once. `projects.stage` stores the furthest
  stage reached; what is actually running is derived from open tasks.
  `03-data-model.md` §3.1
- A blocked stage shows why, inline.
- Delivery is shown greyed with a "not configured yet" note in Phase 1.
  `[CONFIRMED]` A34 defines it as two stages; A2 defers building it.

Under the strip, one line in larger type:

> **Next:** Re-inspection — Site team — nobody has claimed it yet

That line is the client's stated final design principle, rendered.

---

## Sections

Tabs on desktop, an accordion on mobile.

### Tasks
Open tasks first, grouped by department, then completed ones collapsed.
Each row: task, department, who holds it, status, deadline in words, timer if
running, blocker if blocked.

### People and hours
Two clearly separated blocks, because they are two different kinds of number:

**Workshop — live.** Who is working right now, on which task, elapsed time.
**Site — today.** Who was on site today, from the submitted crew log, with a
plain "not yet reported" note if the log has not arrived.

Never merge them into one "people working now" figure.
`06-task-and-time-tracking.md` §5.2

Below: total hours by department for the project so far.

### Samples
The sample chains for this project. Each chain is one thread showing every
attempt, newest first, with status, rejection reason, formula reference and
photo. The attempt count is prominent. `[PROPOSED]` B2

### Site
The site visits, latest first. For each: readiness, date, engineer, the condition
answers, the measurement sheet summary (element count, page count), the signed
scan, and the open corrective actions with the responsible party named.

### Payments
Every installment: number, amount, method, date, status through the cycle, the
proof file, and which journal it went into. A missing installment is shown but
never blocks anything. `[CONFIRMED]` A18

### Activity
The project's event feed, same format as the control room, scoped to this
project.

### Files
Every attachment on the project, grouped by type: payment proofs, sample photos,
formula sheets, signed approval forms, signed site reports, site photos.

---

## Actions

Permission-gated, in an overflow menu rather than a row of buttons:

- Create a sample request (`sample.create`)
- Record a payment (`payment.confirm`)
- Schedule a site visit (`site.visit_create`)
- Add an ad-hoc task (`task.create_adhoc`)
- Change the stage manually (`project.change_stage`)
- Complete the project (`project.complete`) — confirmation dialog, records who
  and when. `[CONFIRMED]` A9. In Phase 1 this is manual; the delivery-plus-final-
  payment rule arrives with Phase 2
- Cancel the project (`project.cancel`) — reason required

---

## What is not on this screen in Phase 1

No cost tab, no material tab, no AI insights panel. `[CONFIRMED]` A2

Leave the space for them rather than filling it. The tab strip should read the
same way when the cost tab is added in Phase 2, so nobody has to relearn the
screen.

---

## States

| State | Behaviour |
|---|---|
| Loading | Header from the list, skeleton sections |
| Lead with nothing yet | The strip shows Lead only; a clear prompt for the first action the viewer is allowed to take |
| Cancelled | A banner across the top with the reason. Everything read-only |
| Completed | A banner with who closed it and when. Read-only except for the audit log |
