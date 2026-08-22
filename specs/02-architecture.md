# 02 — Architecture

---

## 1. Shape of the system

```text
┌──────────────────────────────────────────────┐
│  Next.js frontend (App Router)               │
│  Desktop dashboards + mobile-first task UI   │
│  Arabic / English, RTL                       │
└───────────────┬──────────────────────────────┘
                │ REST + Sanctum token
                │ SSE for the live feed
┌───────────────▼──────────────────────────────┐
│  Laravel API                                 │
│                                              │
│  HTTP layer                                  │
│    Controllers · Form Requests · Resources   │
│  Domain layer                                │
│    Workflow engine · Task service            │
│    Time service · Sample service             │
│    Site service · Payment service            │
│  Support                                     │
│    Events · Listeners · Jobs · Policies      │
│  Gateways                                    │
│    OdooGateway  (fake driver in Phase 1)     │
└───────────────┬──────────────────────────────┘
                │
┌───────────────▼──────────────────────────────┐
│  MySQL 8                                     │
└──────────────────────────────────────────────┘
```

One environment. `[CONFIRMED]` No staging, no separate production. Deployment
is a single Laravel app plus a single Next.js app.

---

## 2. Layering rules

These rules exist so the code stays testable and so the Odoo integration can be
added later without touching business logic.

1. **Controllers do not contain business rules.** They validate input with a
   Form Request, call one domain service, and return a Resource.
2. **The workflow engine is the only thing that creates tasks.** No controller,
   no model observer, no seeder creates a task directly. If a task must be
   created, the engine creates it.
3. **The workflow engine never talks to HTTP and never talks to Odoo.** It
   receives a completed task and produces new tasks and events.
4. **Every state change emits a Laravel event.** Activity feed entries,
   notifications and audit rows are written by listeners, never inline in the
   service. This is what keeps the feed complete without scattering feed code.
5. **Nothing outside `App\Gateways\Odoo` knows Odoo exists.** See section 5.

---

## 3. Laravel structure

> **Superseded in part.** The company Laravel standard requires
> `Form Request → thin Controller → Service → Repository → Resource`, with
> services in `app/Services/` and no `app/Domain/` tree. See
> `15-engineering-standards.md` Part A for the binding layout. The classes below
> and their responsibilities are still correct; only their location changes.

```text
app/
  Domain/
    Workflow/
      WorkflowEngine.php            resolve transitions, create next tasks
      TransitionResolver.php        evaluate conditions
      TaskFactory.php               build a task from a task definition
      TemplateVersionResolver.php
    Tasks/
      TaskService.php               claim, start, pause, block, complete
      TaskValidator.php             required fields + attachments before complete
      DeadlineCalculator.php        SLA + working calendar
    Time/
      TimerService.php              workshop live timers
      CrewLogService.php            site end-of-day logs
      WorkingCalendar.php
    Samples/
      SampleService.php
      SampleChain.php               walk parent links, count attempts
      FormulaService.php
      ApprovalFormGenerator.php     the printable PDF
    Site/
      SiteVisitService.php
      ReadinessService.php          decides what gets blocked
      CorrectiveActionService.php
    Payments/
      PaymentService.php
      JournalService.php            daily collection + close
    Activity/
      ActivityRecorder.php
  Gateways/
    Odoo/
      OdooGateway.php               interface
      FakeOdooGateway.php           Phase 1 — reads seeded tables
      HttpOdooGateway.php           Phase 2 — stub only, not implemented
  Models/
  Policies/
  Http/
    Controllers/Api/V1/
    Requests/
    Resources/
  Events/
  Listeners/
  Jobs/
```

The real paths are in `15-engineering-standards.md` §A2. Repositories,
Form Requests, Resources, Filters, Enums and Policies are mandatory per entity —
see the file checklist there.

---

## 4. Real-time: how "live" actually works

The product's promise is that the dashboard updates as work happens.

**Phase 1 approach `[PROPOSED]`:** Server-Sent Events (SSE), not WebSockets.

Why: the data only flows one way, from server to browser. Nobody types into the
live feed. SSE is one Laravel route returning a stream, works over plain HTTP,
reconnects by itself, and needs no extra server process. WebSockets would need
Reverb or Pusher and gives us nothing extra here.

**How it works:**

- Endpoint: `GET /api/v1/stream` with the user's token.
- The connection stays open. The server pushes events as they are recorded.
- Every event carries an incrementing `id`. On reconnect the browser sends
  `Last-Event-ID` and the server replays anything missed. This is what stops the
  feed from silently losing events when a phone loses signal.
- Events are filtered by permission before they are pushed. A user never
  receives an event about a project they cannot see.

**Fallback:** if SSE is blocked by any network in the company, the frontend
falls back to polling `GET /api/v1/activity?since=<id>` every 15 seconds. The
frontend must be written so the two paths feed the same store.

**Limits:** with under 30 concurrent users this needs no scaling work at all.
If the user count grows past a few hundred, revisit.

---

## 5. The Odoo gateway `[CONFIRMED]` A5

Odoo is not connected in Phase 1. But every place that will one day read Odoo
must go through one interface now, so the change later is small.

```php
interface OdooGateway
{
    public function findClient(string $odooId): ?ClientData;
    public function searchClients(string $query): Collection;
    public function findQuotation(string $number): ?QuotationData;
    public function pushJournal(JournalData $journal): PushResult;
    public function pushPaymentConfirmation(PaymentData $payment): PushResult;
}
```

**Phase 1:** `FakeOdooGateway` reads from local seeded tables and returns the
same shaped objects. `pushJournal` and `pushPaymentConfirmation` record the
attempt in an `odoo_sync_log` table and return success without calling anything.

**Why record the fake pushes:** when the real integration is switched on, that
log shows exactly what would have been sent, which makes the first real sync
verifiable instead of a leap of faith.

**Phase 2 rules, written down now so they are not forgotten:**

- Every push carries an idempotency key so a retry cannot double-post a journal.
- Ownership of every field is decided before the first real call. See `D1`.
- If Odoo is unreachable, the workflow **continues**. The push is queued and
  retried. Operational work is never blocked by an ERP outage.

---

## 6. Background jobs

Queue driver: database. `[PROPOSED]` Single environment, low volume, no Redis
needed. One `queue:work` process under supervisor.

Jobs and schedules:

| Job | When | What it does |
|---|---|---|
| `RecalculateOverdueTasks` | every 10 minutes | Marks tasks whose deadline has passed inside working hours |
| `OpenDailyJournal` | start of the working day | Creates the day's open journal record |
| `CloseStaleTimers` | hourly | Finds timers left running past the end of the shift, stops them at shift end, flags them for supervisor correction |
| `EscalateOverdue` | every 30 minutes | Sends the second-level notification when a task is overdue past its escalation threshold |
| `ProcessOdooSyncQueue` | Phase 2 | Retries failed pushes |

`CloseStaleTimers` matters more than it looks. A supervisor who forgets to press
stop would otherwise record a 14-hour sample task. The job stops the timer at
shift end, marks the entry `needs_review`, and asks the supervisor to confirm
the real hours the next morning.

---

## 7. Files and attachments `[PROPOSED]` B6

- Stored through Laravel's filesystem, local disk in Phase 1.
- Never served directly from a public folder. All downloads go through a
  controller that checks permission first, then streams the file.
- Allowed types: images (jpg, png, heic), PDF, and common office documents.
- Maximum 20 MB per file.
- Images taken on a phone are resized server-side to a maximum of 2000px on the
  long edge before storage. Site photos otherwise fill the disk quickly.
- Files are attached polymorphically, so a task, a sample, a payment and a site
  visit all use the same mechanism.

---

## 7b. Standards

Everything in `15-engineering-standards.md` Part A applies: strict types,
thin controllers, explicit resources, paginated lists, enum catalogs, options
endpoints, eager loading, transactions, Pest tests, and the `composer qa` gate
before done.

## 8. Security

- Sanctum token auth. Tokens expire after 30 days of inactivity. `[PROPOSED]`
- All authorisation through Laravel Policies backed by `spatie/laravel-permission`.
  Never check a role name inside a controller. Check a permission.
- Rate limit: 60 requests per minute per user on normal endpoints, 5 per minute
  on login.
- Every file download and every permission override is written to the audit log.
- Passwords: minimum 10 characters, hashed with bcrypt. No MFA in Phase 1
  `[PROPOSED]` — revisit before the system holds real financial data.

---

## 9. Observability

The system creates work automatically. When automatic creation fails silently,
nobody notices until a project stalls. So:

- Every workflow transition writes a row to `workflow_transition_log`, including
  transitions that were evaluated and **not** taken, with the reason.
- Failed jobs are retained and visible on an admin screen, not only in the
  `failed_jobs` table.
- An admin screen lists tasks that have sat in `ready` for longer than their SLA
  with nobody claiming them. This catches a department queue nobody is watching.

---

## 10. Testing

- Feature tests for every workflow transition in `07-workflows/`. Each business
  flow spec ends with a list of scenarios; each becomes a test.
- Unit tests for `DeadlineCalculator` and `WorkingCalendar`. Date maths around a
  Friday weekend and a holiday is where bugs hide.
- Unit tests for `TimerService`, especially: overlapping timers, timers crossing
  the end of a shift, and paused time.
- A seeded end-to-end test that runs the full demo scenario from
  `13-odoo-gateway-and-seed-data.md` and asserts the correct tasks appeared in
  the correct queues in the correct order.

---

## 11. Non-functional targets `[PROPOSED]`

| Item | Target |
|---|---|
| Concurrent users | 30 |
| Projects in the first year | ~500 |
| Tasks per project | 20–60 |
| API response, list endpoints | under 400 ms |
| API response, task action | under 250 ms |
| Live feed delay | under 2 seconds from action to appearing on another screen |
| Mobile page weight | under 300 KB on the task screen |

The mobile target matters: site supervisors will use this on mobile data in a
half-built building.
