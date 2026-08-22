# Workflow 02 — Sample Request → Approval → Workshop → Client Decision

**Template code:** `sample_request`
**Scope:** one instance per sample. `[CONFIRMED]` A24 means a modification starts
a **new** sample and therefore a **new** instance — see workflow 03.

---

## 1. The flow

```
[Sales] Create sample request
   ▼
[Reception] Review and forward
   ▼
[Approver] Manager approval  ──rejected──▶ [Sales] Revise or cancel
   │ approved
   ▼
[Workshop] Make the sample     (live timer)
   │  Tinting authors the formula in parallel — workflow 04
   ▼
[Reception] Register the formula   — workflow 04
   ▼
[Sales] Print the approval form, get it signed, upload it
   │
   ├─ approved ──▶ sample approved, formula marked approved
   └─ rejected ──▶ modification path — workflow 03
```

Manager approval is **always** required. `[CONFIRMED]` A22

---

## 2. Task definitions

### 2.1 `sales_create_sample_request`

| | |
|---|---|
| Department | `sales` |
| Timer | no |
| SLA | none — this is the flow's entry point |

**Form** — field ownership follows the vision document section 9.1:

| Key | Type | Required | Owner of the value |
|---|---|---|---|
| `client_id` | select | **yes** | Sales. Always required `[CONFIRMED]` A21 |
| `project_id` | select | no | Sales. Empty for a pre-sale sample |
| `color` | text | yes | The client's requirement |
| `texture` | text | no | The client's requirement |
| `client_reference` | text | no | The client's own colour code, photo or physical reference |
| `size` | text | no | Standard company size, prefilled from settings |
| `finish_requirement` | textarea | no | Workshop's responsibility to achieve |
| `needed_by` | date | no | |
| `notes` | textarea | no | |

Formula is deliberately **not** on this form. It comes from Tinting later.
`[CONFIRMED]` A27

**Attachments:** `sample_photo` — optional. A client's reference photo.

**Business rules:**

- Creating a sample with no `project_id` requires `sample.create_presale` and
  sets `is_presale = true`. `[CONFIRMED]` A21
- The reference is generated: `SO9577-S1` with a project, `CL-{client}-S1`
  without one. The reference is never rewritten later. `[PROPOSED]` B1
- `attempt_number = 1`, `root_sample_id = id`.

### 2.2 `reception_review_sample_request`

| | |
|---|---|
| Department | `reception` |
| Timer | no |
| SLA | `[PROPOSED]` `16-sla-defaults.md` — 4 working hours |

Reception's job here is coordination, exactly as described in the vision
document section 4.2: check the request is complete and push it into approval.

**Form:**

| Key | Type | Required |
|---|---|---|
| `review_result` | select (`forward`, `return_to_sales`) | yes |
| `note` | textarea | required when returning |

### 2.3 `manager_approve_sample` `[CONFIRMED]` A22

| | |
|---|---|
| Department | `management` |
| Permission | `sample.approve_manager` |
| Timer | no |
| SLA | `[PROPOSED]` `16-sla-defaults.md` — 4 working hours, escalate at 8 |

**Form:**

| Key | Type | Required |
|---|---|---|
| `decision` | select (`approved`, `rejected`) | yes |
| `comments` | textarea | required when rejected |

Writes a `sample_approvals` row with `type = manager`.

A pre-sale sample is shown to the approver with a clear marker that there is no
paid project behind it. That is the decision they are actually making.

### 2.4 `workshop_make_sample`

| | |
|---|---|
| Department | `workshop` |
| Timer | **yes** `[CONFIRMED]` A12 |
| SLA | `[PROPOSED]` `16-sla-defaults.md` — 3 working days |
| Blocks on site not ready | **no** — workshop work continues `[CONFIRMED]` A29 |

**The task carries:** client, project, salesperson, colour, texture, client
reference, size, finish requirement, deadline, the reference photo, the sample
reference to write on the physical piece, and — if this sample came from a
modification — a link to the parent sample and the reason it was rejected.
`[PROPOSED]` B2

That last item matters. A workshop that cannot see why the last attempt was
rejected will repeat the mistake.

**Form:**

| Key | Type | Required |
|---|---|---|
| `finish_note` | textarea | no |
| `ready_for_registration` | boolean | yes |

**Attachments:** `sample_photo` of the finished piece — `[PROPOSED]` required.
It is the only evidence of what was actually made.

**Business rules:**

- Any running timer stops on completion.
- Sample status → `awaiting_formula_registration`.
- The formula must already have been authored by Tinting (workflow 04). If not,
  the workshop task cannot complete and the error says the formula is missing.

### 2.5 `sales_get_client_decision` `[CONFIRMED]` A23

| | |
|---|---|
| Department | `sales` |
| Timer | no |
| SLA | `[PROPOSED]` `16-sla-defaults.md` — 5 working days, escalate at 10 |
| Starting status | `waiting` — it depends on the client, not on us |

The client approves on **paper**. There is no client portal. `[CONFIRMED]` A23

**The screen provides a Print button** that generates the approval PDF (see
section 4) and records `form_generated_at`.

**Form:**

| Key | Type | Required |
|---|---|---|
| `decision` | select (`approved`, `rejected`) | yes |
| `client_signatory_name` | text | yes — the person who actually signed |
| `decided_at` | date | yes — the date **on the form**, not today |
| `comments` | textarea | required when rejected |

**Attachments:** `client_approval_form` — the signed scan. **Required.**
A client decision with no signed form cannot be recorded.

`decided_at` is separated from the upload time deliberately. A form signed on
Sunday and scanned on Wednesday must report as Sunday, or every lead-time report
is wrong.

**Business rules:**

- `approved` → sample `approved`; the sample's current formula →
  `formula.status = approved`; `samples.approved_formula_id` is set; a
  `sample_approvals` row with `type = client` is written; the project stage moves
  past `sample` if nothing else is open.
- `rejected` → sample `rejected_by_client`, and the modification workflow becomes
  available to Sales. It is **not** started automatically — the client may reject
  and walk away, and auto-creating work for a dead deal would be wrong.

---

## 3. Transitions

| From | To | Condition |
|---|---|---|
| — | `sales_create_sample_request` | — |
| `sales_create_sample_request` | `reception_review_sample_request` | — |
| `reception_review_sample_request` | `manager_approve_sample` | `review_result = forward` |
| `reception_review_sample_request` | `sales_fix_sample_request` | `review_result = return_to_sales` |
| `sales_fix_sample_request` | `reception_review_sample_request` | — |
| `manager_approve_sample` | `workshop_make_sample` | `decision = approved` |
| `manager_approve_sample` | `sales_sample_rejected` | `decision = rejected` |
| `workshop_make_sample` | `reception_register_formula` | — (workflow 04) |
| `reception_register_formula` | `sales_get_client_decision` | — |

---

## 4. The printed client approval form `[CONFIRMED]` A23

Generated as a PDF from the system. Bilingual, mirroring the layout of the
company's existing site report so it looks like a Colortek document.

Contents:

- Colortek header and logo
- Date, project name, client name, quotation number (`SO9577`)
- Sample reference — printed large, because it must match what is written on the
  physical sample
- Colour, texture, client reference, size, finish requirement
- Attempt number and, if this is not the first attempt, the previous sample
  reference and the reason it was rejected
- Two decision boxes: موافق / Approved and غير موافق / Not approved
- A comments area
- Two signature lines: توقيع العميل (client) and توقيع مندوب المبيعات (sales)

`[PROPOSED]` — this layout is our design. If the company already prints a sample
approval sheet, replace this with theirs, exactly as we did with the site report.
Ask before building it.

---

## 5. Test scenarios

1. A sample with no client cannot be created.
2. A sample with no project requires `sample.create_presale`.
3. Manager approval is always created; there is no path from Reception to
   Workshop that skips it.
4. Rejection by the manager stops the flow and does not create a workshop task.
5. The workshop task cannot complete before a formula has been authored.
6. The workshop timer stops on completion and the hours land on the named
   employee, not the supervisor who pressed the button.
7. A client decision cannot be recorded without the signed form file.
8. `decided_at` is stored as the date on the form, not the upload date.
9. Approval marks exactly one formula as approved and sets
   `approved_formula_id`.
10. Rejection does **not** automatically create a modification.
11. A workshop sample task stays claimable while the project's site is Not Ready.
