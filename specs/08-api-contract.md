# 08 — API Contract

REST, JSON, versioned under `/api/v1`. Auth: Laravel Sanctum bearer tokens.
`[PROPOSED]` B7

---

## 1. Conventions

**Success**

```json
{ "data": { ... }, "meta": { ... } }
```

**List**

```json
{ "data": [ ... ],
  "meta": { "current_page": 1, "per_page": 25, "total": 132 },
  "links": { "next": "...", "prev": null } }
```

**Error**

```json
{ "message": "The payment proof is required before this task can be completed.",
  "errors": { "attachments.payment_proof": ["A payment proof file must be attached."] },
  "code": "task.missing_required_attachment" }
```

Every business rule failure returns a machine-readable `code` and a message that
names the specific thing that is missing. Never "validation failed".

| Status | Used for |
|---|---|
| 200 | success |
| 201 | created |
| 204 | deleted |
| 401 | not authenticated |
| 403 | authenticated, no permission |
| 404 | not found, or found but not visible to this user (never leak existence) |
| 409 | conflict — e.g. the task was already claimed by someone else |
| 422 | validation or business rule failure |
| 429 | rate limited |

**Headers:** `Accept-Language: ar` or `en` selects the response language for
labels and messages. `[CONFIRMED]` A32

**Pagination:** every list endpoint returns a paginator, `per_page` default
**15**. Only `/options/*` and `/enums/*` return a plain collection.
`15-engineering-standards.md` §A7.

**Relations:** every detail endpoint accepts `?relations=a,b`. Resources gate
relations with `whenLoaded()` and the service eager-loads them. Nothing is lazy
loaded. `15-engineering-standards.md` §A3.

**Dates:** ISO 8601 with offset. The server sends UTC; the client renders in
`Africa/Cairo`.

**Idempotency:** `POST` endpoints that create work — task completion, claim,
timer start — accept an `Idempotency-Key` header. A repeat with the same key
returns the original result instead of acting twice. This is what makes a
double-tap on a phone safe.

---

## 2. Auth

| Method | Path | Notes |
|---|---|---|
| POST | `/auth/login` | email + password → token |
| POST | `/auth/logout` | |
| GET | `/auth/me` | user, roles, permissions, departments, locale |

`/auth/me` returns the **permission list**, not the role names. The frontend
shows and hides on permissions, matching the server. `[CONFIRMED]` A4

---

## 3. Tasks — the core of the API

| Method | Path | Permission |
|---|---|---|
| GET | `/tasks` | `task.view_own_queue` |
| GET | `/tasks/{id}` | |
| POST | `/tasks/{id}/claim` | `task.claim` |
| POST | `/tasks/{id}/release` | `task.release` |
| POST | `/tasks/{id}/start` | |
| POST | `/tasks/{id}/pause` | |
| POST | `/tasks/{id}/resume` | |
| POST | `/tasks/{id}/block` | `task.block` |
| POST | `/tasks/{id}/unblock` | `task.unblock` |
| POST | `/tasks/{id}/complete` | `task.complete` |
| POST | `/tasks/{id}/comments` | `task.comment` |
| POST | `/tasks/{id}/attachments` | |
| PATCH | `/tasks/{id}/deadline` | `task.override_deadline` |
| POST | `/tasks/{id}/reassign` | `task.reassign` |
| POST | `/tasks` | `task.create_adhoc` |

`GET /tasks` filters: `scope` (`my` / `queue` / `all`), `department_id`,
`project_id`, `status[]`, `overdue`, `priority`, `q`, `due_before`, `sort`.

`scope=my` returns tasks this user has claimed. `scope=queue` returns `ready`
tasks in their departments. These are the two screens a worker uses all day.

### `POST /tasks/{id}/claim`

Returns `409` with code `task.already_claimed` if someone got there first,
including who has it. The frontend shows a plain message and refreshes the list.

### `POST /tasks/{id}/complete`

```json
{
  "fields": { "amount": 50000, "method": "bank_transfer", "paid_at": "2026-08-20" },
  "attachment_ids": [412]
}
```

Response includes what was created — this is what lets the UI show the handover:

```json
{
  "data": { "id": 88, "status": "completed" },
  "meta": {
    "created_tasks": [
      { "id": 89, "reference": "SO9577-T013",
        "title": "Review payment", "department": "Reception",
        "status": "ready", "due_at": "2026-08-20T13:00:00Z" }
    ],
    "project_stage": "payment"
  }
}
```

The frontend shows: *"Done. Reception now has 'Review payment', due today at
4pm."* That single sentence is the product.

---

## 4. Projects

| Method | Path |
|---|---|
| GET | `/projects` |
| POST | `/projects` |
| GET | `/projects/{id}` |
| PATCH | `/projects/{id}` |
| GET | `/projects/{id}/workflow` — stages, open tasks, what is next |
| GET | `/projects/{id}/tasks` |
| GET | `/projects/{id}/samples` |
| GET | `/projects/{id}/site-visits` |
| GET | `/projects/{id}/payments` |
| GET | `/projects/{id}/activity` |
| GET | `/projects/{id}/hours` |
| POST | `/projects/{id}/complete` — `project.complete` |
| POST | `/projects/{id}/cancel` |

`GET /projects/{id}/workflow` is the data behind the live workflow strip: every
stage, its state, who currently holds work, and the single next expected action.

---

## 5. Payments and journal

| Method | Path |
|---|---|
| GET | `/payments` |
| GET | `/payments/{id}` |
| POST | `/projects/{id}/payments` — starts a `payment_cycle` instance |
| GET | `/journals` |
| GET | `/journals/{date}` |
| POST | `/journals/{date}/submit` — `journal.prepare` |
| POST | `/journals/{date}/reopen` — `journal.reopen`, reason required |

---

## 6. Samples and formula

| Method | Path |
|---|---|
| GET | `/samples` |
| POST | `/samples` |
| GET | `/samples/{id}` |
| GET | `/samples/{id}/chain` — the whole attempt thread |
| POST | `/samples/{id}/modification` — `sample.request_modification` |
| POST | `/samples/{id}/approval-form` — generates the printable PDF |
| POST | `/samples/{id}/client-decision` — signed scan required |
| GET | `/samples/{id}/formulas` |
| POST | `/samples/{id}/formulas` — `formula.author` |
| POST | `/formulas/{id}/register` — `formula.register` |
| PATCH | `/formulas/{id}` — `formula.update_registered`, audited |

`GET /samples/{id}/chain` returns every sample sharing a `root_sample_id`,
ordered by attempt, each with its status, rejection reason, formula and photos.
`[PROPOSED]` B2

---

## 7. Site

| Method | Path |
|---|---|
| GET | `/site-visits` |
| POST | `/projects/{id}/site-visits` |
| GET | `/site-visits/{id}` |
| PATCH | `/site-visits/{id}` — draft only |
| POST | `/site-visits/{id}/measurements` — bulk upsert of rows and deductions |
| POST | `/site-visits/{id}/submit` |
| POST | `/site-visits/{id}/readiness` — `site.set_readiness` |
| GET | `/site-visits/{id}/pdf` — reproduces the paper form |
| GET | `/site-checklist-items` |
| GET/POST/PATCH | `/corrective-actions` |
| POST | `/tasks/{id}/override-site-block` — `site.override_block`, reason required |

Measurements are saved in **bulk**, not row by row. A site engineer on weak
mobile data entering 40 rows must not depend on 40 successful requests. The
frontend keeps a local draft and pushes the whole sheet at once, with an
`Idempotency-Key`.

---

## 8. Time

| Method | Path |
|---|---|
| POST | `/tasks/{id}/timer/start` |
| POST | `/tasks/{id}/timer/stop` |
| GET | `/timers/active` — the caller's running timer |
| PATCH | `/time-entries/{id}` — `time.correct`, note required |
| GET | `/crew-logs` |
| POST | `/projects/{id}/crew-logs` |
| PATCH | `/crew-logs/{id}` |
| POST | `/crew-logs/{id}/submit` |
| GET | `/employees` |

`POST /timer/start` accepts `employee_id` so a supervisor can run the timer for a
named worker. `[CONFIRMED]` A12

---

## 9. Live feed

| Method | Path |
|---|---|
| GET | `/stream` | SSE. Replays from `Last-Event-ID` |
| GET | `/activity` | Paginated history, `since` and `project_id` filters |
| GET | `/notifications` | |
| POST | `/notifications/{id}/read` | |
| POST | `/notifications/read-all` | |

Both `/stream` and `/activity` apply the row-level visibility rules from
`04-permissions-and-roles.md` §4 **in the query**. A user never receives an event
about a project they cannot see.

---

## 10. Dashboards

| Method | Path |
|---|---|
| GET | `/dashboard/control-room` |
| GET | `/dashboard/workshop` |
| GET | `/dashboard/site` |
| GET | `/dashboard/samples` |
| GET | `/search?q=` |

Each returns a whole screen's data in one response. Ten small requests to paint
a dashboard is what makes an operations screen feel slow, and slow is the one
thing that will stop people using this.

---

## 11. Admin

| Method | Path |
|---|---|
| GET/POST/PATCH | `/admin/users`, `/admin/roles`, `/admin/employees` |
| GET/POST | `/admin/workflow-templates` |
| POST | `/admin/workflow-templates/{id}/publish` — creates the next version |
| GET/PATCH | `/admin/settings` |
| GET | `/admin/holidays` |
| GET | `/admin/stalled-instances` |
| GET | `/admin/failed-jobs` |
| GET | `/audit-logs` — `audit.view` |

---

## 11b. Enum catalogs and options

The frontend never hardcodes an option list. `15-engineering-standards.md` §A5, §A6.

| Method | Path | Returns |
|---|---|---|
| GET | `/enums/{name}` | `[{ "value": "in_progress", "label": "In progress" }]` in the request locale |
| GET | `/options/departments` | id + label |
| GET | `/options/users` | |
| GET | `/options/employees` | |
| GET | `/options/clients` | |
| GET | `/options/projects` | |
| GET | `/options/blocker-categories` | |
| GET | `/options/checklist-items` | The five site condition items |

Enum names: `task_status`, `task_priority`, `project_stage`, `project_status`,
`payment_method`, `payment_status`, `journal_status`, `sample_status`,
`formula_status`, `site_readiness`, `corrective_action_status`,
`responsible_party`, `approval_type`, `approval_decision`, `attachment_type`,
`time_entry_source`.

Options endpoints are filtered by the caller's visibility, exactly like the list
endpoints. An options list is a data leak if it is not.

## 12. Files

| Method | Path |
|---|---|
| POST | `/attachments` — multipart, returns an id to attach on completion |
| GET | `/attachments/{id}` | permission checked, then streamed |
| DELETE | `/attachments/{id}` | only while the owning record is unlocked |

Files are uploaded first and referenced by id when completing a task. A site
engineer with a weak connection uploads photos as they take them, then completes
the task in one small request. Bundling files into the completion request would
mean a failed upload loses the whole form.
