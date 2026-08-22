# 14 — Phase 2 Backlog

Everything the client described that Phase 1 deliberately leaves out.
`[CONFIRMED]` A2.

Nothing here is cancelled. It is parked, with the reason it was parked and what
must be answered before it can start.

---

## 1. Odoo integration

The largest item. Phase 1 built the seam; Phase 2 fills it.
`13-odoo-gateway-and-seed-data.md`

**Blocked on:** `[TBD]` D1 — Odoo version, hosting, enabled modules; D2 — whether
staff have Odoo accounts.

Work: replace `FakeOdooGateway` with `HttpOdooGateway`, decide the field
ownership matrix, build the sync queue with idempotency and retry, backfill
existing clients and quotations, and verify the first real pushes against the
`odoo_sync_log` rows Phase 1 recorded.

---

## 2. Materials and the quantity engine

The vision document's sections 25 to 28.

**Phase 1 already gives it a head start.** The site measurement sheet is captured
structurally — elements, dimensions, heights and typed deductions with signs —
so Phase 2 begins with real measured data rather than an empty table.
`07-workflows/05` §5

Still needed:

- The material master and technical data sheets: coverage rate, consumption rate,
  coats, mixing ratio, wastage, unit cost, versioned
- Which material and how many coats apply to each measured element `[TBD]` D14
- Whether `area_sqm` is computed or typed `[TBD]` C6
- Whether deduction kinds are a fixed list `[TBD]` C7
- Wastage rules per product `[TBD]` D8
- Warehouse issue and return records
- How actual consumption is measured in reality `[TBD]` D7 — **the hardest
  question in the whole product.** Planned versus actual is meaningless until it
  is answered, and no amount of design work substitutes for the answer

---

## 3. Cost model

The vision document's sections 29 to 33.

**Blocked on:** D3 labour rate method, D4 overhead allocation, D5 margin rules,
D10 transportation process.

Phase 1 already records the two inputs that cost needs most: **hours** (timers
and crew logs) and **material movement is not yet recorded, but sample attempts
and their hours are**. When the rate model is approved, sample and workshop
labour cost can be computed retrospectively over Phase 1 data. That is the reason
time tracking was in Phase 1 and cost was not.

Also needed: transportation records, expense capture, cost timeline, planned
versus actual versus forecast, and the permission work so cost is invisible to
workers. `activity_events.visible_to_permission` already exists for this.
`03-data-model.md` §10

---

## 4. Delivery

`[CONFIRMED]` A34 — two separate stages:

1. **Material delivery** — workshop to site. Transport, receipt, signature.
2. **Final handover** — the finished work to the client. Inspection, acceptance,
   a signed handover document.

Different owners, different documents. Both are configurable stages in the engine
already; neither has task definitions yet.

Once Delivery exists, project completion moves from the manual close of Phase 1
to the client's rule: delivery confirmed **and** final payment received.
`[CONFIRMED]` A9

---

## 5. The AI layer

The vision document's sections 40 to 42.

Deliberately last, because an assistant over incomplete data produces confident
wrong answers, and one of those destroys trust in the whole system.

Design work required before any of it is built:

- **How the model reads the data.** Typed tool calls over the existing services,
  not text-to-SQL. The permission filters already exist; the assistant must go
  through them, never around them
- **Row-level security in answers.** A worker asking about cost must get the same
  refusal the API gives them. This is the single highest risk in the feature
- **Prompt injection.** Blocker reasons, site notes and formula text are typed by
  users and flow into any summary. They are untrusted input
- **Forecasting is arithmetic, not language.** A deterministic engine computes it;
  the model explains it. Never the other way round
- **Anomaly thresholds** — numeric, configurable, and derived from real data once
  a year of it exists
- **Evaluation** — a fixed question set with known answers, run on every change

---

## 6. Individual worker accounts

Phase 1: supervisors log the team's time. `[CONFIRMED]` A11, A13

If the company later wants per-worker logins, the model already supports it —
`employees.user_id` exists. The change is account provisioning, a simplified
mobile screen, and a decision about whether crew logs then disappear or remain as
a fallback for the day a phone is flat.

---

## 7. Notifications beyond in-app

`[CONFIRMED]` A33 — Phase 1 is in-app only.

Phase 2: email, and WhatsApp if approved. Every notification is already a Laravel
Notification class on the `database` channel, so adding a channel is a channel
list and a template, not a rewrite.
`10-notifications-and-activity-stream.md` §3

Also: per-user channel preferences, quiet hours, and a daily digest for the three
admin failure lists in `11-audit-and-exceptions.md` §6.

---

## 8. Structured formulas

Phase 1 stores a formula as free text or a scanned sheet. `[CONFIRMED]` A25

When Phase 2 needs material cost per sample, this becomes structured lines: a
base product plus colorants with quantities and units. Existing free-text records
stay as history and are not migrated — they simply predate the change.

This is a planned step, recorded here so it is not experienced as a surprise
rewrite. `07-workflows/04` §2

---

## 9. Reporting

The vision document's section 52. Phase 1 has dashboards; it has no report
builder, no export and no scheduled reports.

Highest value first, judged by what the client asked about most:

1. Sample history and attempt counts per project
2. Worker hours by project and by department
3. Blocker aging
4. Workflow SLA performance by task type
5. Site readiness history

Each needs an export. Phase 1's dashboards answer "now"; reports answer "over the
last quarter", and management will ask for the second one within a month of using
the first.

---

## 10. Smaller items

| Item | Note |
|---|---|
| Scope changes and variation orders | Not covered anywhere. Client changes scope mid-execution; the quotation changes and the plan must follow |
| Payment exceptions | Partial payment, overpayment, bounced transfer, refund, credit note |
| Purchase requests | Phase 1 blocks a task with `missing_material`; Phase 2 should be able to raise a request from that blocker |
| Rework, warranty and snag lists | After delivery exists |
| Subcontractors and external labour | A cost category with no home yet |
| Client access | `[TBD]` D12 — whether the client ever logs in. Phase 1's answer is no, and paper approval works |
| Multi-factor authentication | Deferred `[PROPOSED]`. Revisit before the system holds real financial data |
| Audit retention policy | `[TBD]` D13 — until answered, nothing is deleted |
| Health and safety incidents | Site work, not modelled |
| Batch and lot tracking | Comes with the material master |
