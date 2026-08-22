# Workflow 04 — Formula: Authoring and Registration

**Template code:** `formula_registration`
**Scope:** one formula version per sample attempt.

---

## 1. The separation that must be preserved

The client was explicit, and the vision document repeats it in sections 4.5 and
11.2:

- **Tinting authors** the formula. They do the technical work.
- **Reception registers** it in the system, after Workshop returns the sample and
  the paperwork.

Two different people, two different timestamps, both stored. `[CONFIRMED]` A27

This is not bureaucracy. It is the traceability that lets the company answer
"who made this colour" and "who wrote it down" separately when a formula turns
out to be wrong.

---

## 2. What a formula is in Phase 1 `[CONFIRMED]` A25

A free-text note, or a scanned photo of the tinting sheet, or both. Versioned.
**Not** structured base-and-colorant lines.

| Stored | Field |
|---|---|
| The recipe as written | `formulas.body` (text) |
| Or the scanned sheet | an `attachments` row of type `formula_sheet` |
| Who made it | `author_employee_id` / `author_user_id` |
| When they made it | `authored_at` |
| Who typed it in | `registered_by_user_id` |
| When they typed it in | `registered_at` |
| Version | `formulas.version`, increments per sample |

At least one of `body` or a `formula_sheet` attachment must be present.

**Consequence to accept now:** a free-text formula cannot be costed, cannot be
searched by ingredient, and cannot be used by a future quantity engine. When
Phase 2 needs material cost per sample, this becomes structured lines and the
existing free-text records stay as history. That migration is recorded in
`14-phase-2-backlog.md` so it is a planned step rather than a surprise.

Visibility: anyone who can see the project can see the formula. `[CONFIRMED]` A26
No field-level secrecy in Phase 1.

---

## 3. The flow

```
[Workshop] Make the sample  ────────┐  (workflow 02)
                                    │
[Tinting] Author the formula  ──────┤  runs in parallel, on the same sample
                                    │
                                    ▼
                        both complete
                                    ▼
                  [Reception] Register the formula
                                    ▼
                  [Sales] Get the client decision
```

`join_mode = all` on the registration task. Reception cannot register a formula
that has not been written, and the sample is not ready until it is made.

---

## 4. Task definitions

### 4.1 `tinting_author_formula`

| | |
|---|---|
| Department | `tinting` |
| Permission | `formula.author` |
| Timer | **yes** — tinting hours belong to the sample |
| SLA | `[PROPOSED]` `16-sla-defaults.md` — 1 working day |
| Created | at the same time as `workshop_make_sample` |

**Form:**

| Key | Type | Required |
|---|---|---|
| `body` | textarea | required unless a `formula_sheet` file is attached |
| `author_employee_id` | employee | yes — the person who actually mixed it |
| `authored_at` | date | yes |
| `notes` | textarea | no |

**Attachments:** `formula_sheet` — the photo of the paper sheet. Required unless
`body` is filled.

**Business rules:**

- Creates a `formulas` row, status `draft`, version = previous version on this
  sample + 1.
- Reference `SO9577-S1-F1`. `[PROPOSED]` B1
- The author is recorded as an **employee**, not necessarily a user, because the
  tinting staff may not have logins. `[CONFIRMED]` A11

### 4.2 `reception_register_formula`

| | |
|---|---|
| Department | `reception` |
| Permission | `formula.register` |
| Timer | no |
| SLA | `[PROPOSED]` `16-sla-defaults.md` — 4 working hours |
| Join | `all` — waits for both the workshop and the tinting task |

**The screen shows:** the sample, its photo, the authored formula text and the
scanned sheet, the author's name, and the previous version if this is a repeat
attempt — so Reception can see what changed.

**Form:**

| Key | Type | Required |
|---|---|---|
| `confirm_matches_sheet` | boolean | yes |
| `corrections` | textarea | no — used when Reception fixes a transcription |
| `notes` | textarea | no |

**Business rules:**

- Formula status `draft` → `registered`.
- `registered_by_user_id` and `registered_at` are set from the acting user and
  the clock. They are never editable by hand.
- If Reception enters `corrections`, the original `body` is **not** overwritten.
  The correction is appended with an audit row recording both values. A
  transcription correction that destroys the original is how a formula becomes
  untraceable.
- Sample status → `ready_for_client_approval`.

### 4.3 Later status changes

- When the client approves the sample, the current formula →
  `status = approved` and `samples.approved_formula_id` is set. (Workflow 02.)
- When a modification supersedes a sample, that sample's formula →
  `superseded`. It is never deleted.

Editing a registered formula requires `formula.update_registered` and always
writes an audit row with the old and new text. `[CONFIRMED]` A27's separation is
meaningless if the record can be quietly rewritten.

---

## 5. Transitions

| From | To | Condition | Join |
|---|---|---|---|
| `manager_approve_sample` | `workshop_make_sample` | `decision = approved` | — |
| `manager_approve_sample` | `tinting_author_formula` | `decision = approved` | — |
| `workshop_make_sample` | `reception_register_formula` | — | `all` |
| `tinting_author_formula` | `reception_register_formula` | — | `all` |
| `reception_register_formula` | `sales_get_client_decision` | — | — |

The two parallel tasks are created by the same approval. This is the clearest
example of the parallel branching described in `05-workflow-engine.md` §7.

---

## 6. Test scenarios

1. Approval creates both the workshop task and the tinting task at once, in
   different queues.
2. The registration task stays `waiting` until both have completed.
3. Completing only the tinting task does not create the registration task.
4. A formula with neither text nor a scanned sheet cannot be saved.
5. The author is stored as an employee with no user account, and displays
   correctly.
6. `registered_by_user_id` is the acting user and cannot be set through the API.
7. A Reception correction preserves the original text and writes an audit row.
8. Version numbers increment per sample, so a second sample in a chain starts its
   own `F1`.
9. Client approval marks exactly one formula approved.
10. Superseding a sample marks its formula superseded, never deleted.
