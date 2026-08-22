# Workflow 01 — Payment → Reception → Journal → Accounting

**Template code:** `payment_cycle`
**Scope:** one instance per **installment**. `[CONFIRMED]` A17
**Trigger:** Sales records that a client payment has arrived.

This is the flow the client described most precisely, and the one that proves
the product. Today Sales tells Reception on WhatsApp. After this, Sales finishes
a task and Reception's task appears already holding everything.

---

## 1. The flow

```
[Sales] Confirm payment
   │  requires: amount, method, paid_at, quotation locked, PROOF FILE
   ▼
[Reception] Review payment
   │  requires: nothing new — Reception reviews what Sales sent
   ▼
[Reception] Daily journal          (one per day for the whole company)
   │  collects every payment reviewed that day
   ▼
[Accounting] Process journal
   ▼
done
```

---

## 2. Task definitions

### 2.1 `sales_confirm_payment`

| | |
|---|---|
| Department | `sales` |
| Timer | no |
| SLA | `[PROPOSED]` `16-sla-defaults.md` — 4 working hours from creation |
| Blocks on site not ready | no |

**Form:**

| Key | Type | Required | Notes |
|---|---|---|---|
| `installment_number` | number | yes | 1, 2, 3… |
| `amount` | money | yes | |
| `method` | select | yes | bank_transfer / cash / cheque / other |
| `paid_at` | date | yes | The date the client paid, not today |
| `quotation_locked` | boolean | yes | Must be true to complete |
| `notes` | textarea | no | |

**Required attachments:** `payment_proof` — **mandatory, no exception.**
`[CONFIRMED]` A19

**Business rules on complete:**

1. `payment_proof` must exist. Without `payment.skip_proof` — a permission
   granted to nobody by default — the task cannot complete. The error names the
   missing file.
2. `quotation_locked` must be true. In Phase 1 this is a checkbox Sales ticks
   after locking it in Odoo, plus we set `quotations.locked_at`. In Phase 2 the
   gateway verifies it.
3. Creates or updates the `payments` row, status → `confirmed`.
4. If this is installment 1 and the project stage is `lead` or `quotation`, the
   stage moves to `payment`.

**Activity event:** `payment.confirmed`, severity `success`.

### 2.2 `reception_review_payment`

| | |
|---|---|
| Department | `reception` |
| Timer | no |
| SLA | `[PROPOSED]` `16-sla-defaults.md` — 4 working hours |

**The task screen shows, without Reception asking anyone:** project, client,
salesperson, quotation number and value, installment number, amount, method,
payment date, the proof file inline, and Sales' notes.

Reception re-enters **nothing**. `[CONFIRMED]` — this is the explicit
requirement from the vision document, section 8.3.

**Form:**

| Key | Type | Required |
|---|---|---|
| `review_result` | select (`accepted`, `query`) | yes |
| `review_note` | textarea | required when `query` |

**Business rules:**

- `accepted` → payment status `reviewed`, attach to today's open journal, and
  the transition to the journal task fires.
- `query` → **no** journal entry. A new `sales_clarify_payment` task is created
  back in the Sales queue carrying Reception's note. When Sales completes it,
  the review task is recreated. This is the rejection path the vision document
  asked for but never described.

### 2.3 `reception_daily_journal` `[CONFIRMED]` A20

One per calendar day for the whole company. Not one per payment, not one per
person.

| | |
|---|---|
| Department | `reception` |
| Timer | no |
| SLA | end of the working day |

**Creation:** the `OpenDailyJournal` job creates the day's `journals` row and its
task at the start of the working day, whether or not any payment exists yet. A
day with no payments is submitted as an empty journal, or auto-closed by the job
the next morning with status `submitted` and a zero total. Deciding this now
avoids a queue slowly filling with abandoned empty journals.

**The screen shows:** the day's date, a table of every reviewed payment with
project, client, amount and method, the running total, and a link to each proof.

**Form:**

| Key | Type | Required |
|---|---|---|
| `odoo_journal_ref` | text | no in Phase 1 `[CONFIRMED]` A5 — required in Phase 2 |
| `notes` | textarea | no |

**Business rules:**

1. At least one payment, or an explicit "empty day" confirmation.
2. On complete: `journals.status` → `submitted`, `submitted_at` set, each
   payment → `journaled`, and `journal_payment.amount_snapshot` is written.
3. The snapshot matters: correcting a payment amount next week must not silently
   change a journal Accounting already processed.
4. A submitted journal is read-only. Reopening needs `journal.reopen` and is
   audited.

### 2.4 `accounting_process_journal`

| | |
|---|---|
| Department | `accounting` |
| Timer | no |
| SLA | `[PROPOSED]` `16-sla-defaults.md` — 1 working day |

**The screen shows:** the whole journal, every payment, every proof file, who
confirmed and who reviewed each one, and the total.

**Form:**

| Key | Type | Required |
|---|---|---|
| `accounting_result` | select (`processed`, `query`) | yes |
| `odoo_reference` | text | no in Phase 1 |
| `note` | textarea | required when `query` |

**Business rules:**

- `processed` → journal `accounted`, every payment → `accounted`, instance
  completes.
- `query` → a `reception_fix_journal` task goes back to Reception with the note.
  The journal reopens automatically for that correction, and the reopening is
  audited even though it was system-driven.

---

## 3. Transitions

| From | To | Condition |
|---|---|---|
| — (instance start) | `sales_confirm_payment` | — |
| `sales_confirm_payment` | `reception_review_payment` | — |
| `reception_review_payment` | `reception_daily_journal` | `review_result = accepted` |
| `reception_review_payment` | `sales_clarify_payment` | `review_result = query` |
| `sales_clarify_payment` | `reception_review_payment` | — |
| `reception_daily_journal` | `accounting_process_journal` | — |
| `accounting_process_journal` | `reception_fix_journal` | `accounting_result = query` |
| `reception_fix_journal` | `accounting_process_journal` | — |

The journal task is shared by many payment instances. It is created once per day
against the `journals` record and linked to each payment instance, rather than
created once per payment. `[PROPOSED]` — this is the one place where an instance
does not own its task exclusively, and it is deliberate, because the client's
process is one journal per day. `[CONFIRMED]` A20

---

## 4. What this flow never does

- It never blocks operational work. A missing or late installment does not stop
  the workshop or the site. `[CONFIRMED]` A18
- It never writes to Odoo in Phase 1. `[CONFIRMED]` A5 — the intended push is
  recorded in `odoo_sync_log` so the first real sync can be checked against it.

---

## 5. Test scenarios

1. Sales cannot complete without a proof file.
2. Sales cannot complete with `quotation_locked` false.
3. On completion, a Reception task appears immediately carrying the amount,
   method, proof and salesperson, with nothing re-entered.
4. Reception `accepted` attaches the payment to today's journal.
5. Reception `query` creates a Sales task and attaches nothing to the journal.
6. Three payments reviewed on the same day appear in one journal, not three.
7. A submitted journal is read-only.
8. Changing a payment amount after journal submission does not change the
   journal total.
9. Accounting `query` reopens the journal, creates a Reception task, and writes
   an audit row.
10. Installment 2 on the same project runs a second, independent instance while
    installment 1 is still open.
11. A day with no payments does not leave a stuck open task.
