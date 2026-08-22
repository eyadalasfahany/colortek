# Screen — Roles, Permissions and Users

**Route:** `/admin/access`
**Who:** super admin only
**Data:** `GET /admin/roles`, `GET /admin/permissions`, `GET /admin/users`

`[CONFIRMED]` A4b — roles and permissions are managed through a screen, not in
code. The super admin creates roles, chooses their permissions, and assigns roles
to people.

---

## 1. Super admin versus admin

`04-permissions-and-roles.md` listed one `admin` role. The client's answer splits
it in two:

| Role | Can do |
|---|---|
| `super_admin` | Everything, **including creating roles, editing permissions and assigning roles to users**. The only role that can grant power |
| `admin` | Operational administration: users' details, employees, settings, calendar, holidays, workflow templates, site checklist items. **Cannot** change roles or permissions |

Why separate: the person who maintains the working calendar and the workflow
templates is not necessarily the person who should be able to grant themselves
the ability to approve samples or see the audit log.

`super_admin` is protected:

- It cannot be deleted.
- Its permission set cannot be edited — it holds everything by definition.
- The last remaining `super_admin` cannot have the role removed, and cannot
  deactivate themselves. Locking everyone out of the permission system is a
  mistake with no recovery path inside the application.

---

## 2. Tab 1 — Roles

A list of roles with the number of permissions and the number of users holding
each.

**Creating or editing a role** opens the permission picker: every permission from
`04-permissions-and-roles.md` §2, grouped by area (Projects, Tasks, Payments,
Samples, Site, Time, Administration), each with its plain-language description
from that file — not the raw `project.change_stage` string alone.

Rules:

- Permission names are **seeded and fixed**. A super admin composes roles from
  them; they cannot invent a new permission, because a permission with no code
  checking it does nothing. The screen says this.
- A role in use cannot be deleted until its users are moved. The screen names
  how many users would be affected and offers to reassign them.
- Every change writes an audit row with the old and new permission sets.
  `11-audit-and-exceptions.md` §2

**Dangerous permissions are marked.** These grant the ability to bypass a control
the rest of the system depends on:

| Permission | Why it is marked |
|---|---|
| `site.override_block` | Starts site work on a site declared unsafe or unready |
| `payment.skip_proof` | Completes a payment with no proof. Granted to nobody by default `[CONFIRMED]` A19 |
| `journal.reopen` | Reopens an accounting record |
| `formula.update_registered` | Rewrites a registered formula |
| `time.correct` | Edits recorded hours |
| `task.override_deadline` | Makes SLA reporting meaningless if given widely |
| `audit.view` | Reads the record of everyone's actions |

Granting one shows a confirmation naming what it allows. Not a block — a pause.

---

## 3. Tab 2 — Users

A list: name, email, roles, departments, active, last seen.

**Editing a user:**

- Roles — multi-select. A user may hold several. `[PROPOSED]` B9
- Departments — multi-select, each with a supervisor flag. The supervisor flag is
  what allows logging hours for other people. `03-data-model.md` §2
- Language — `en` or `ar`. `[CONFIRMED]` A32
- Active — deactivating keeps all history; the user simply cannot log in

**The effective-permission view** is the most useful part of this screen. After
choosing roles, it shows the complete resulting permission list for that person,
merged across their roles, in plain language:

> Ahmed will be able to: see all projects · confirm payments · create sample
> requests · record client decisions · claim and complete tasks in Sales.
> He will **not** be able to: approve sample requests · see the audit log.

A super admin assembling roles from a checklist cannot otherwise tell what they
have actually granted. Showing the merged result, in sentences, is what prevents
someone quietly gaining an override permission through a second role.

**Deactivating a user** who currently holds claimed tasks warns first and offers
to release them back to their department queues. Otherwise the work sits claimed
by someone who cannot log in, and the department never sees it.
`06-task-and-time-tracking.md` §2

---

## 4. Tab 3 — Employees

The workers who do **not** log in. `[CONFIRMED]` A11

Name, staff code, department, active. Optionally linked to a user account if that
person also logs in.

This list is what supervisors pick from when logging crew hours or attributing a
timer, so it must be maintainable by an admin without a developer. An employee
with recorded hours can be deactivated but never deleted.

---

## 5. Assigning the Approver role

This closes open question C5. `[CONFIRMED]` A4b

The `approver` role exists in the seed, holding `sample.approve_manager`. The
super admin assigns it to whichever people the company chooses, and can change
that at any time without a code change. The spec does not need to name anybody.

Guard: if **no** active user holds `sample.approve_manager`, every sample request
will stall at the approval step with nobody able to act. The system detects this
and shows a warning on the admin screen and in the stalled-instances list:

> No active user can approve sample requests. New sample requests will stop at
> the approval step.

`[PROPOSED]` — the same check applies to any permission that a workflow task
depends on. A department queue nobody can act on is the most likely real failure
of this system in its first month. `11-audit-and-exceptions.md` §6

---

## 6. API

| Method | Path | Permission |
|---|---|---|
| GET | `/admin/roles` | `role.manage` |
| POST | `/admin/roles` | `role.manage` |
| PATCH | `/admin/roles/{id}` | `role.manage` |
| DELETE | `/admin/roles/{id}` | `role.manage` |
| GET | `/admin/permissions` | `role.manage` — grouped, with descriptions |
| GET | `/admin/users` | `user.manage` |
| POST | `/admin/users` | `user.manage` |
| PATCH | `/admin/users/{id}` | `user.manage` |
| POST | `/admin/users/{id}/roles` | `role.assign` |
| GET | `/admin/users/{id}/effective-permissions` | `role.manage` |
| GET | `/admin/access/coverage` | `role.manage` — which required permissions nobody holds |
| GET/POST/PATCH | `/admin/employees` | `employee.manage` |

Two new permissions, added to `04-permissions-and-roles.md` §2:

| Permission | Meaning |
|---|---|
| `role.assign` | Give or remove a role on a user. Held by `super_admin` only |
| `holiday.manage` | Add, edit and delete holidays. Held by `super_admin` and `admin` |

`role.manage` (editing what a role contains) stays with `super_admin` only.
`admin` keeps `user.manage` for names, emails, languages and departments, but
not `role.assign` — an operational admin can maintain people without being able
to change what those people are allowed to do.

---

## 7. States

| State | Behaviour |
|---|---|
| Loading | Skeleton |
| Not super admin | The Roles tab is hidden entirely, not shown disabled |
| Last super admin | Their own role checkbox is locked, with the reason shown |
| Role in use | Delete is blocked, naming the users who hold it |
| Coverage gap | A warning banner naming the permission nobody holds |

---

## 8. Tests

1. A non-super-admin cannot reach any role endpoint.
2. `admin` can edit a user's details but cannot assign a role.
3. The last `super_admin` cannot lose the role or deactivate themselves.
4. A role in use cannot be deleted.
5. Effective permissions merge correctly across two roles.
6. Every role change writes an audit row with the old and new permission sets.
7. Removing the last holder of `sample.approve_manager` raises the coverage
   warning.
8. Deactivating a user with claimed tasks offers to release them, and releasing
   returns them to the correct department queues.
