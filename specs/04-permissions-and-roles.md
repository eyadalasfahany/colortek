# 04 — Roles and Permissions

Implemented with `spatie/laravel-permission`. `[CONFIRMED]` A4

Two rules that must never be broken:

1. **Never check a role name in code.** Always check a permission. Roles are a
   convenient bundle of permissions that an admin can change; permissions are
   what the code depends on.
2. **Authorisation lives in Policies**, not in controllers. A controller calls
   `$this->authorize(...)` and nothing else.

---

## 1. Roles `[PROPOSED]`

| Role | Who | Notes |
|---|---|---|
| `super_admin` | 1 person | Everything, **and the only role that can create roles, edit permissions and assign roles to users**. `[CONFIRMED]` A4b. Protected: cannot be deleted, and the last holder cannot lose it |
| `admin` | 1–2 | Operational administration: users' details, employees, settings, calendar, holidays, workflow templates, checklist items. **Cannot** change roles or permissions |
| `management` | Owner / GM | Sees everything, approves, overrides. Does not configure |
| `approver` | assigned by the super admin | The sample approval role. `[CONFIRMED]` A22 — one fixed role, A4b — the people holding it are chosen in the system, not named here |
| `sales` | Sales staff | Projects, payments, sample requests, client approvals |
| `reception` | Reception | Payment review, journal, sample coordination, formula registration |
| `accounting` | Accounting | The journal queue |
| `workshop_supervisor` | Workshop | Workshop queue, timers, crew for the workshop |
| `tinting` | Tinting | Formula authoring |
| `site_engineer` | Project engineers | Site visits, readiness, crew logs |
| `viewer` | Anyone who only watches | Read-only across projects |

A user may hold several roles. `[PROPOSED]` B9 — in a company this size, one
person is often both Reception and Accounting.

---

## 2. Permissions

Named `<area>.<action>`. This is the full Phase 1 list.

### Projects
| Permission | Meaning |
|---|---|
| `project.view` | See projects |
| `project.view_all` | See every project, not just ones they are involved in |
| `project.create` | |
| `project.update` | |
| `project.change_stage` | Move a project between stages manually |
| `project.complete` | Close a project. `[CONFIRMED]` A9 |
| `project.cancel` | |

### Tasks
| Permission | Meaning |
|---|---|
| `task.view_own_queue` | See their department's queue |
| `task.view_all` | See every queue |
| `task.claim` | Take a task from the queue |
| `task.release` | Put a claimed task back |
| `task.complete` | Finish a task they hold |
| `task.block` | Mark blocked |
| `task.unblock` | Clear a blocker |
| `task.comment` | |
| `task.reassign` | Move a task to a different department or force-release someone else's claim |
| `task.create_adhoc` | Create a task outside a workflow template |
| `task.cancel` | |
| `task.override_deadline` | Change one task's due date. `[CONFIRMED]` A15 |

### Payments and journal
| Permission | Meaning |
|---|---|
| `payment.view` | |
| `payment.confirm` | Sales confirms a payment was received |
| `payment.review` | Reception reviews it |
| `payment.skip_proof` | **Not granted to anyone by default.** `[CONFIRMED]` A19 says proof is mandatory. The permission exists so the rule can be relaxed later without a code change |
| `journal.view` | |
| `journal.prepare` | Reception submits the day's journal |
| `journal.account` | Accounting processes it |
| `journal.reopen` | Reopen a submitted journal. Management only. Always audited |

### Samples and formula
| Permission | Meaning |
|---|---|
| `sample.view` | |
| `sample.create` | Raise a sample request |
| `sample.create_presale` | Raise one with no project. `[CONFIRMED]` A21 |
| `sample.approve_manager` | The manager approval. Held by `approver` only. `[CONFIRMED]` A22 |
| `sample.record_client_decision` | Enter the signed client form |
| `sample.request_modification` | Create the linked follow-up sample |
| `sample.cancel` | |
| `formula.view` | Everyone with project access has this. `[CONFIRMED]` A26 |
| `formula.author` | Tinting writes the recipe |
| `formula.register` | Reception enters it into the system. `[CONFIRMED]` A27 |
| `formula.update_registered` | Correct a registered formula. Always audited |

### Site
| Permission | Meaning |
|---|---|
| `site.view` | |
| `site.visit_create` | Start a site visit report |
| `site.visit_submit` | Submit and lock it |
| `site.set_readiness` | Declare Ready / Not Ready |
| `site.override_block` | **Start site work while the site is Not Ready.** `[CONFIRMED]` A31. Requires a written reason, always audited |
| `site.corrective_action_manage` | Create, assign and close corrective actions |
| `site.measurements_edit` | Edit the measurement sheet after submission |

### Time
| Permission | Meaning |
|---|---|
| `time.timer_run` | Start and stop a workshop timer |
| `time.timer_run_for_others` | Run a timer on behalf of a named employee |
| `time.crew_log_submit` | Submit the end-of-day site crew log |
| `time.correct` | Edit a recorded time entry after the fact. Always audited |
| `time.view_all` | See hours across all projects and people |

### Administration
| Permission | Meaning |
|---|---|
| `user.manage` | Create and edit users: name, email, language, departments, active |
| `role.manage` | Create roles and choose what permissions they contain. `super_admin` only |
| `role.assign` | Give or remove a role on a user. `super_admin` only |
| `holiday.manage` | Add, edit and delete holidays. `[CONFIRMED]` A14c |
| `employee.manage` | The non-login worker list |
| `workflow.view` | See workflow templates |
| `workflow.manage` | Edit and publish templates |
| `settings.manage` | Calendar, shift hours, holidays, thresholds |
| `audit.view` | Read the audit log |
| `client.manage` | |
| `quotation.manage` | |

---

## 3. Role to permission matrix

`•` = granted.

| Permission | super | admin | mgmt | approver | sales | recept | acct | wshop | tint | site | viewer |
|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| project.view | • | • | • | • | • | • | • | • | • | • | • |
| project.view_all | • | • | • |  |  | • | • |  |  |  | • |
| project.create | • | • | • |  | • |  |  |  |  |  |  |
| project.update | • | • | • |  | • | • |  |  |  | • |  |
| project.change_stage | • | • | • |  |  | • |  |  |  |  |  |
| project.complete | • | • | • |  |  |  |  |  |  |  |  |
| project.cancel | • | • | • |  |  |  |  |  |  |  |  |
| task.view_own_queue | • | • | • | • | • | • | • | • | • | • |  |
| task.view_all | • | • | • |  |  | • |  |  |  |  | • |
| task.claim | • | • | • | • | • | • | • | • | • | • |  |
| task.release | • | • | • | • | • | • | • | • | • | • |  |
| task.complete | • | • | • | • | • | • | • | • | • | • |  |
| task.block | • | • | • | • | • | • | • | • | • | • |  |
| task.unblock | • | • | • | • | • | • | • | • | • | • |  |
| task.comment | • | • | • | • | • | • | • | • | • | • |  |
| task.reassign | • | • | • |  |  | • |  |  |  |  |  |
| task.create_adhoc | • | • | • |  |  | • |  |  |  | • |  |
| task.cancel | • | • | • |  |  |  |  |  |  |  |  |
| task.override_deadline | • | • | • |  |  | • |  |  |  |  |  |
| payment.view | • | • | • |  | • | • | • |  |  |  | • |
| payment.confirm | • | • |  |  | • |  |  |  |  |  |  |
| payment.review | • | • |  |  |  | • |  |  |  |  |  |
| payment.skip_proof | • |  |  |  |  |  |  |  |  |  |  |
| journal.view | • | • | • |  |  | • | • |  |  |  | • |
| journal.prepare | • | • |  |  |  | • |  |  |  |  |  |
| journal.account | • | • |  |  |  |  | • |  |  |  |  |
| journal.reopen | • | • | • |  |  |  |  |  |  |  |  |
| sample.view | • | • | • | • | • | • |  | • | • | • | • |
| sample.create | • | • | • |  | • | • |  |  |  |  |  |
| sample.create_presale | • | • | • |  | • |  |  |  |  |  |  |
| sample.approve_manager | • | • | • | • |  |  |  |  |  |  |  |
| sample.record_client_decision | • | • |  |  | • | • |  |  |  |  |  |
| sample.request_modification | • | • |  |  | • |  |  |  |  |  |  |
| sample.cancel | • | • | • |  | • |  |  |  |  |  |  |
| formula.view | • | • | • | • | • | • | • | • | • | • | • |
| formula.author | • | • |  |  |  |  |  | • | • |  |  |
| formula.register | • | • |  |  |  | • |  |  |  |  |  |
| formula.update_registered | • | • | • |  |  | • |  |  |  |  |  |
| site.view | • | • | • | • | • | • |  | • |  | • | • |
| site.visit_create | • | • |  |  |  |  |  |  |  | • |  |
| site.visit_submit | • | • |  |  |  |  |  |  |  | • |  |
| site.set_readiness | • | • | • |  |  |  |  |  |  | • |  |
| site.override_block | • | • | • |  |  |  |  |  |  |  |  |
| site.corrective_action_manage | • | • | • |  |  | • |  |  |  | • |  |
| site.measurements_edit | • | • | • |  |  |  |  |  |  | • |  |
| time.timer_run | • | • |  |  |  |  |  | • | • | • |  |
| time.timer_run_for_others | • | • |  |  |  |  |  | • | • | • |  |
| time.crew_log_submit | • | • |  |  |  |  |  | • |  | • |  |
| time.correct | • | • | • |  |  |  |  | • |  | • |  |
| time.view_all | • | • | • |  |  | • |  |  |  |  | • |
| user.manage | • | • |  |  |  |  |  |  |  |  |  |
| role.manage | • |  |  |  |  |  |  |  |  |  |  |
| role.assign | • |  |  |  |  |  |  |  |  |  |  |
| holiday.manage | • | • |  |  |  |  |  |  |  |  |  |
| employee.manage | • | • | • |  |  | • |  | • |  | • |  |
| workflow.view | • | • | • |  |  | • |  |  |  |  |  |
| workflow.manage | • | • |  |  |  |  |  |  |  |  |  |
| settings.manage | • | • |  |  |  |  |  |  |  |  |  |
| audit.view | • | • | • |  |  |  |  |  |  |  |  |
| client.manage | • | • | • |  | • | • |  |  |  |  |  |
| quotation.manage | • | • | • |  | • | • |  |  |  |  |  |

`super` is `super_admin`. It holds every permission by definition and its set is
not editable. `[CONFIRMED]` A4b — see `09-screens/07-admin-roles-and-permissions.md`.

Note the split on administration: `admin` keeps `user.manage` and
`holiday.manage`, but **not** `role.manage` or `role.assign`. An operational
admin can maintain people and the calendar without being able to change what
those people are allowed to do.

`payment.skip_proof` is intentionally granted to nobody. It exists so the rule
can be loosened later by an admin, not by a developer.

---

## 4. Row-level visibility

Permission alone is not enough. A user with `project.view` but not
`project.view_all` sees only projects where they are:

- the salesperson, or
- the responsible user, or
- the claimant of any task on that project, or
- a member of a department that currently holds an open task on that project.

That last rule matters. It is what lets a workshop supervisor see the project
context of the sample they are making, and stop seeing it when the work moves on.

The same filter is applied to:

- the activity stream (`activity_events.visible_to_permission` plus the project
  filter),
- global search,
- notifications.

A permission check that is only applied in the UI is not a permission check.
Every list endpoint applies this filter in the query, server side.

---

## 5. Overrides

Three actions in Phase 1 are overrides. Each one: requires its own permission,
requires a typed reason, writes an `audit_logs` row with `event = 'override'`,
and produces a `warning`-severity activity event that management sees.

| Override | Permission |
|---|---|
| Start site work while the site is Not Ready | `site.override_block` |
| Complete a payment task without proof | `payment.skip_proof` |
| Reopen a submitted journal | `journal.reopen` |

The roles screen marks these, plus `formula.update_registered`, `time.correct`,
`task.override_deadline` and `audit.view`, as dangerous permissions. Granting one
shows a confirmation naming what it allows — a pause, not a block.
`09-screens/07-admin-roles-and-permissions.md` §2

The system never silently allows an exception. It allows it loudly.
