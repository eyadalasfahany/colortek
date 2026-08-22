# 13 — The Odoo Gateway and Seed Data

`[CONFIRMED]` A5 — no real Odoo integration in Phase 1. The system is built on
seeded data behind a gateway, so the integration can be added after the system is
accepted without rewriting business logic.

---

## 1. The gateway

One interface, `App\Gateways\Odoo\OdooGateway`. Nothing outside
`app/Gateways/Odoo/` knows Odoo exists. `02-architecture.md` §5.

Two implementations:

| Driver | Phase | Behaviour |
|---|---|---|
| `FakeOdooGateway` | 1 | Reads local tables. Push calls are recorded, not sent |
| `HttpOdooGateway` | 2 | Real calls. A stub in Phase 1 that throws if resolved |

Bound in `AppServiceProvider` from `config('services.odoo.driver')`.

### Recording the pushes that did not happen

`FakeOdooGateway::pushJournal()` and `pushPaymentConfirmation()` write to
`odoo_sync_log` with `status = 'simulated'` and the full payload they would have
sent, then return success.

This is worth the small effort. When the real integration is switched on, that
table holds months of exactly what the system intended to send, so the first real
sync can be checked against reality instead of being a leap of faith.

### Rules that carry into Phase 2

Written now so they are not rediscovered later:

1. Every push carries an idempotency key. A retry must not double-post a journal.
2. Field ownership is decided before the first real call — which system owns each
   field. `[TBD]` D1. Without it the two systems will fight over the same values.
3. If Odoo is unreachable, the workflow **continues**. The push queues and
   retries. Operational work is never blocked by an ERP outage.
4. Pull is scheduled, not on demand. A user waiting on a screen must never wait
   on Odoo.

---

## 2. What the fake driver serves

| Entity | Phase 1 source |
|---|---|
| Clients | The local `clients` table, entered or seeded |
| Quotations | The local `quotations` table. Number typed by Sales, e.g. `SO9577` |
| Projects | Created by Sales in our system. `[CONFIRMED]` A6 |
| Payments | Recorded in our system |
| Journals | Prepared in our system, `odoo_journal_ref` left empty |
| Employees | The local `employees` table |

Nothing is imported from Odoo in Phase 1. If the client can export a client and
quotation list to CSV, importing it once makes the prototype far more convincing
— but it is optional and changes nothing structural.

---

## 3. Seed data

Three seeders, run in order.

### 3.1 `ReferenceSeeder` — always run, in every environment

Data the system cannot function without:

- **Departments:** sales, reception, accounting, workshop, tinting, site,
  management, admin
- **Roles and permissions:** the full matrix from
  `04-permissions-and-roles.md` §3
- **Blocker categories:** the four confirmed categories, with
  `missing_material` notifying the workshop and `site_not_ready` notifying the
  site team. `[CONFIRMED]` A16
- **Site checklist items:** the five items transcribed from the real paper form,
  with the exact Arabic wording. `07-workflows/05` §1. `[CONFIRMED]` A28
- **Settings:** `work_start = "09:00"`, `work_end = "17:00"` `[CONFIRMED]` A14b,
  `weekend_days = ["friday"]`, default locale, humidity maximum, repeat-attempt
  threshold, block-all-on-site-not-ready = false
- **Roles:** `super_admin`, `admin`, `management`, `approver`, `sales`,
  `reception`, `accounting`, `workshop_supervisor`, `tinting`, `site_engineer`,
  `viewer`, each with the permission set from `04-permissions-and-roles.md` §3.
  The super admin can change all of it afterwards through the screen
  `[CONFIRMED]` A4b
- **Default SLA per task type** from `16-sla-defaults.md`, marked in the admin
  screen as proposed and awaiting confirmation
- **Workflow templates:** the five Phase 1 templates, version 1, published.
  `05-workflow-engine.md` §12

**Holidays are not seeded.** `[CONFIRMED]` A14c — an admin adds them through
`09-screens/06-admin-calendar-and-holidays.md`. The demo environment may seed a
handful so the calendar preview is not empty, but the production seeder must
leave the table empty rather than guess a national holiday list.

The SLA defaults are `[PROPOSED]`. They are editable in the admin screen, so the
client corrects them in the system rather than in a document — but review them
before the pilot.

### 3.2 `DemoSeeder` — the prototype

One realistic project carried end to end, so the client can see the product work
rather than read about it.

Modelled on the real signed form in `docs/`:

| | |
|---|---|
| Client | Omega |
| Project | `SO9577` — Omega — Mahmoud Eslily |
| Address | New Giza |
| Quotation | `SO9577`, value `[PROPOSED]` EGP 480,000 |
| Salesperson | one seeded Sales user |
| Site engineer | one seeded Site user |

The demo runs the flow the client described, and stops mid-project on purpose so
the live states are all visible at once:

1. Installment 1, EGP 240,000, confirmed with a proof image, reviewed by
   Reception, in yesterday's journal, processed by Accounting — **complete**
2. Sample `SO9577-S1` — requested, approved by the manager, made, formula
   `SO9577-S1-F1` authored and registered, rejected by the client:
   *"too dark"*
3. Sample `SO9577-S2` — created by modification, approved, made, formula
   `SO9577-S2-F1`, **approved by the client** with a signed form on file
4. Site visit `SO9577-SV1` — 18 September, with the **real measurements** from
   the supplied form: reception walls, reception ceiling and stairs, the
   deductions for the entrance door and the windows, and the boxed heights
5. Condition statement — other contractors still on site, so **Not Ready**
6. Two corrective actions open, both the client's responsibility
7. Two workshop tasks running with live timers, proving that site tasks are held
   while workshop preparation continues. `[CONFIRMED]` A29
8. Installment 2 confirmed by Sales this morning, waiting in Reception's queue
9. A blocked tinting task: *missing material*
10. Roughly forty activity events across the last three days

Using the real measurements matters. A demo with invented numbers gets reviewed
on its numbers. A demo with the site engineer's own sheet gets reviewed on the
workflow.

Seed two more projects in lighter detail, so the control room and the lists are
not showing a single row.

### 3.3 `UserSeeder`

One user per role, with obvious names and a documented shared password for the
demo environment. Every one must be replaced before real use — the deployment
checklist says so explicitly.

Exactly one `super_admin` is created. It is the only account that can then build
the real roles and assign them. `[CONFIRMED]` A4b

The `approver` role is seeded with its permission but the client chooses who
holds it, in the system. `09-screens/07-admin-roles-and-permissions.md` §5

---

## 4. Test factories

Every entity gets a factory. States that must exist, because the tests in
`05-workflow-engine.md` §13 and the workflow specs depend on them:

| Factory | States |
|---|---|
| `TaskFactory` | `ready`, `claimed`, `inProgress`, `blocked`, `overdue`, `siteHeld` |
| `ProjectFactory` | `lead`, `inSample`, `inSite`, `blocked`, `completed` |
| `SampleFactory` | `presale`, `awaitingApproval`, `inWorkshop`, `approved`, `rejected`, `withChain(3)` |
| `SiteVisitFactory` | `ready`, `notReady`, `withMeasurements(20)` |
| `PaymentFactory` | `confirmed`, `reviewed`, `journaled` |
| `TimeEntryFactory` | `running`, `closed`, `autoClosed` |

`withChain(3)` builds a three-attempt sample chain with the parent, root and
attempt numbers set correctly. It is the fixture for the counting tests in
`07-workflows/03` §6.

---

## 5. The end-to-end test

One test runs the whole demo flow through the API, as the real users, and asserts
after each step that the correct task appeared in the correct department queue
with the correct status.

If that test passes, the product's central claim is true. It is the most valuable
test in the suite and should be written early, not last.
