# 00 — Overview and Glossary

**Status:** Phase 1 specification
**Stack:** Laravel + MySQL (API), Next.js (frontend), single environment
**Source vision document:** `specs/AI-Powered Live Project Workflow System — Claude Design Specification.md`

Every statement in this spec set is tagged:

| Tag | Meaning |
|---|---|
| `[CONFIRMED]` | The client decided this. Do not change it without asking. |
| `[PROPOSED]` | Our suggestion. Sensible default, open to correction. |
| `[TBD]` | Unknown. Must not be invented. See `01-decisions-and-open-questions.md`. |

---

## 1. What we are building

Colortek runs its business on Odoo. Odoo holds clients, quotations, payments and
accounting. It does not tell anyone what to do next.

Today, work moves between departments by phone call, WhatsApp and paper. Sales
tells Reception a client paid. Reception tells the Workshop a sample is needed.
Nobody has one place that shows what is happening right now.

We are building a **live workflow layer on top of Odoo**. Not a replacement for
Odoo. `[CONFIRMED]`

The one rule that defines the whole product:

> When a person finishes their task, the system automatically creates the next
> task, gives it to the right department, and carries all the information and
> files with it.

Nobody should ever have to ask "who does the next step, and did they get the
information?"

---

## 2. What Phase 1 covers `[CONFIRMED]`

**In:**

- Projects, clients, quotations (entered in our system, not yet synced from Odoo)
- The workflow engine: tasks, automatic handover, dependencies, deadlines
- Payment → Reception review → Daily Journal → Accounting
- Sample request → manager approval → workshop → formula → client approval
- Sample modification (creates a new linked sample)
- Site visit report, site readiness, corrective actions, re-inspection
- Time tracking: live timers in the workshop, end-of-day crew logs on site
- Live activity stream, in-app notifications, audit log
- Role and permission system
- Arabic + English interface with RTL

**Out — moved to Phase 2:**

- Material master and technical data sheets
- Quantity calculation engine
- Planned vs actual material consumption
- Project cost model, labour rates, cost forecasting
- The AI assistant and anomaly detection
- Delivery workflow
- Odoo integration (real API calls)
- Transportation and expense tracking
- Individual worker logins

Phase 2 items are recorded in `14-phase-2-backlog.md` so nothing is lost.

---

## 3. Who uses it in Phase 1 `[CONFIRMED]`

| Group | Roughly | What they do |
|---|---|---|
| Sales | few | Create projects, confirm payments, request samples, record client approval |
| Reception | 1–3 | Review payments, prepare the daily journal, coordinate samples, register formulas |
| Accounting | 1–2 | Process the daily journal |
| Manager / Approver | 1–2 | Approve sample requests, override blocks |
| Workshop supervisor | 1–2 | Claim sample and production tasks, run timers |
| Tinting supervisor | 1 | Create formulas |
| Site supervisor / project engineer | few | Site visits, readiness reports, daily crew logs |
| Admin | 1 | Users, roles, workflow templates, settings |

Individual workers do **not** log in. Their hours are recorded by their
supervisor. `[CONFIRMED]`

---

## 4. Glossary

Terms used in the same meaning across every spec file.

| Term | Meaning |
|---|---|
| **Project** | One job for one client. Created by Sales. Carries a reference derived from the Odoo quotation number. |
| **Client** | The customer company or person. Comes from Odoo later; entered manually in Phase 1. |
| **Quotation** | The priced offer produced in Odoo. In Phase 1 we store its number and value only, not its lines. |
| **Stage** | Where a project is in its life: Lead, Quotation, Payment, Sample, Site, Production, Execution, Delivery, Completed. |
| **Workflow template** | The configured recipe for a flow: which tasks exist, who owns each, what comes after what. |
| **Workflow instance** | One live run of a template, attached to a project or a sample. |
| **Task** | One unit of work owned by one department. The atom of the whole system. |
| **Department queue** | The inbox of a department. Tasks land here; a member claims one to start it. |
| **Claim** | A user taking a queued task for themselves. Starts the timer where timers apply. |
| **Blocked** | Work cannot continue because of a problem. Requires a category and a written reason. |
| **Waiting** | Work has not started because it is legitimately waiting for something expected. Not a problem. |
| **Sample** | A physical test piece made in the workshop so the client can see the colour, texture and finish. |
| **Sample chain** | A sample plus every later sample created from it by a modification request. Shown as one thread. |
| **Formula** | The tinting recipe for a sample. In Phase 1 a note or a scanned photo, versioned. |
| **Formula author** | The Tinting person who created it. |
| **Formula registrar** | The Reception person who entered it into the system. Deliberately a different person. |
| **Site visit** | An inspection of the client's location to check whether work can begin. |
| **Site readiness** | The result of the inspection: Ready, Not Ready, Re-inspection required. |
| **Corrective action** | A task created when a site fails inspection, to fix what failed. |
| **Crew log** | One end-of-day record by a site supervisor: who worked, on what, for how long. |
| **Daily journal** | One record per day of every payment Reception reviewed that day, handed to Accounting. |
| **Installment** | One scheduled payment of the contract. Every installment runs the full payment workflow. |
| **Working calendar** | Friday weekend, fixed daily shift, official holidays. Deadline clocks only run inside it. |
| **SLA** | The target time a task type should take. Drives the "overdue" flag. |
| **Activity event** | One line in the live company feed. Immutable. |
| **Audit log** | The permanent record of who changed what, from what value to what value, and why. |
| **Odoo gateway** | The Laravel layer that will later talk to Odoo. In Phase 1 it reads seeded data. |

---

## 5. Words we deliberately avoid

- **"Assigned to"** for tasks. Tasks belong to a *department queue* until someone
  *claims* them. Saying "assigned" implies a named person and that is wrong here.
- **"Revision"** for samples. A modification creates a **new sample** linked to
  its parent. The word revision would suggest editing the old record, which we
  never do.
- **"Overdue"** as a status. It is a computed flag on top of a status. See
  `06-task-and-time-tracking.md`.

---

## 6. How to read this spec set

Read in this order:

1. `01-decisions-and-open-questions.md` — what is settled and what is not
2. `02-architecture.md` — how the system is put together
3. `03-data-model.md` — the tables
4. `04-permissions-and-roles.md` — who may do what
5. `05-workflow-engine.md` — the heart of the product
6. `06-task-and-time-tracking.md` — task lifecycle and hours
7. `07-workflows/` — the five real business flows
8. `08-api-contract.md` — endpoints
9. `09-screens/` — the interface
10. Everything else as needed
