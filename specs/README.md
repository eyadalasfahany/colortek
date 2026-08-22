# Colortek — Specification Set

Phase 1 of the AI-Powered Live Project Workflow System.

**Stack:** Laravel + MySQL API, Next.js frontend, single environment.

---

## Read in this order

| File | What it covers |
|---|---|
| [00-overview-and-glossary.md](00-overview-and-glossary.md) | What we are building, what is in Phase 1, the vocabulary |
| [01-decisions-and-open-questions.md](01-decisions-and-open-questions.md) | Every confirmed decision, every proposal, every open question. **The register — check here first** |
| [15-engineering-standards.md](15-engineering-standards.md) | The company Laravel and Next.js standards. **Overrides anything else in this set** |
| [02-architecture.md](02-architecture.md) | How the system is put together |
| [03-data-model.md](03-data-model.md) | The tables |
| [04-permissions-and-roles.md](04-permissions-and-roles.md) | Who may do what |
| [05-workflow-engine.md](05-workflow-engine.md) | The heart of the product |
| [06-task-and-time-tracking.md](06-task-and-time-tracking.md) | Task lifecycle, timers, crew logs, the working calendar |
| [07-workflows/](07-workflows/) | The five real business flows |
| [08-api-contract.md](08-api-contract.md) | Endpoints |
| [09-screens/](09-screens/) | The interface |
| [10-notifications-and-activity-stream.md](10-notifications-and-activity-stream.md) | The live feed and notifications |
| [11-audit-and-exceptions.md](11-audit-and-exceptions.md) | The audit trail and every exception case |
| [12-i18n-and-rtl.md](12-i18n-and-rtl.md) | Arabic, English, RTL |
| [13-odoo-gateway-and-seed-data.md](13-odoo-gateway-and-seed-data.md) | The Odoo seam and the demo data |
| [14-phase-2-backlog.md](14-phase-2-backlog.md) | Everything parked, and why |
| [16-sla-defaults.md](16-sla-defaults.md) | Proposed deadline per task type, all admin-editable |

The original client vision document is kept as
`AI-Powered Live Project Workflow System — Claude Design Specification.md`.
It is the source of the concept but **not** the source of truth — where it
disagrees with these files, these files win. The contradictions are listed in
`01` Part E.

Source documents from the client live in `docs/`: the two blank site visit report
pages and two real filled examples.

---

## Tags

| Tag | Meaning |
|---|---|
| `[CONFIRMED]` | The client decided this. Do not change it without asking |
| `[PROPOSED]` | Our suggestion. Sensible default, open to correction |
| `[TBD]` | Unknown. Must not be invented |

---

## Still waiting on the client

**Nothing blocks Phase 1 any more.** See `01` Part C.

Closed: the site visit report (C1), the quotation number format (C2), shift hours
and holidays (C3), and who approves samples (C5).

Two questions shape the Phase 2 quantity engine but do not block Phase 1:

| # | Question |
|---|---|
| C6 | Is the area column on the site report computed by us, or typed by the engineer? |
| C7 | Are deduction types a fixed list (door, window, opening) or always free text? |

One item is worth reviewing but is editable in the system rather than blocking:
the proposed deadlines in [16-sla-defaults.md](16-sla-defaults.md).
