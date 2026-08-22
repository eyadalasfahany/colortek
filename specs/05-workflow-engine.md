# 05 — Workflow Engine

This is the heart of the product. Everything else is a screen on top of it.

The engine's only job:

> When a task finishes, work out what happens next, create those tasks in the
> right department queues, and carry the information with them.

---

## 1. Rules the engine obeys

1. **Only the engine creates workflow tasks.** Controllers, observers and
   seeders never call `Task::create()`. `[PROPOSED]`
2. **The engine is synchronous.** When a user presses Complete, the next task
   exists before the HTTP response returns. The user must see the handover
   happen. Notifications and feed writes happen after, in listeners.
3. **A task completion either fully succeeds or fully fails.** The whole
   completion runs in one database transaction: validate, save outputs, mark
   complete, evaluate transitions, create next tasks. A crash halfway must not
   leave a completed task with no successor. This is the single most important
   correctness rule in the system.
4. **The engine never reads Odoo.** `[CONFIRMED]` A5
5. **Instances are pinned to a template version.** `[PROPOSED]` B5

---

## 2. Template versioning `[PROPOSED]` B5

A workflow template describes tasks and the arrows between them. People will
edit templates while dozens of projects are running.

Rules:

- A template is identified by `code` + `version`.
- A published template is **immutable**. Editing it creates version N+1 as a
  draft.
- Publishing version N+1 sets `is_active = false` on version N.
- **New** instances use the active version. **Running** instances keep the
  version they started on and finish under its rules.
- A draft template cannot be instantiated.

Why this matters: without it, an admin who deletes a task definition on Monday
breaks every project that was mid-flow. With it, nothing in flight changes.

Deactivating a template does not cancel running instances.

---

## 3. What happens when a task is completed

`TaskService::complete($task, $user, $inputs, $files)`

```
BEGIN TRANSACTION
 1. Authorise: the user holds the task and has task.complete
 2. Validate required_fields against form_schema
 3. Validate required_attachment_types are present
 4. Run task-type business rules (see 07-workflows/)
 5. Stop any running timer on this task
 6. Save task_field_values
 7. status -> completed, completed_at, completed_by_user_id
 8. Write task_status_events row
 9. Engine: find transitions where from_task_definition_id = this definition
10. For each transition, evaluate its condition
11. Log every evaluation to workflow_transition_log, taken or not
12. For each taken transition:
      a. check the join_mode of the target
      b. if satisfied, build the next task from its definition
      c. copy the context (see section 5)
      d. compute the deadline
      e. decide the starting status: ready, waiting or pending
13. If no transitions were taken and no tasks remain open,
      mark the workflow instance completed
14. Recompute the project stage
COMMIT

AFTER COMMIT (listeners)
15. Write activity_events
16. Send in-app notifications to the target departments
17. Push to the SSE stream
```

Steps 15–17 are outside the transaction on purpose. A failed notification must
never roll back real work.

---

## 4. Conditions

A transition may carry a condition. If it is null, the transition always fires.

`[PROPOSED]` condition shape — deliberately small. A workflow condition language
that can do anything becomes a programming language nobody can debug.

```json
{ "field": "decision", "operator": "equals", "value": "approved" }
```

```json
{ "all": [
    { "field": "decision", "operator": "equals", "value": "rejected" },
    { "field": "sample.attempt_number", "operator": "lt", "value": 4 }
]}
```

Operators: `equals`, `not_equals`, `in`, `not_in`, `gt`, `gte`, `lt`, `lte`,
`is_empty`, `is_not_empty`.

Combinators: `all`, `any`, `none`.

Field sources, resolved in this order:
1. `task_field_values` of the completed task (plain key, e.g. `decision`)
2. The workflow subject, dot notation (e.g. `sample.attempt_number`,
   `project.stage`, `payment.amount`)
3. A setting (e.g. `setting.block_all_when_site_not_ready`)

Anything not resolvable is treated as empty and the evaluation is logged with
the reason. It never throws. A workflow must not stop because someone renamed
a field.

---

## 5. What travels with the task

This is the feature the client actually asked for: Reception should not have to
be told the payment details, they should already be in the task.

When the engine creates a task it copies:

| What | From |
|---|---|
| `project_id` | the instance |
| `subject_type` / `subject_id` | the instance (sample, payment, site visit) |
| `title`, `instructions` | the task definition, **copied**, not referenced |
| `department_id` | the task definition |
| `priority` | the task definition, unless the project raises it |
| `due_at` | `DeadlineCalculator` |

The task screen then **reads through** to the subject rather than duplicating
its data. A Reception payment task shows the amount, the method, the proof file
and who confirmed it by loading the `payments` row and its attachments — not by
copying them into the task.

Copy titles and instructions, read through for data. Copying the title means
history stays readable after a template is edited. Reading through for data
means a corrected amount is corrected everywhere.

Every task screen also shows the **previous task's outputs and files**, so the
person always sees what the last person did without opening anything.

---

## 6. Joins: several predecessors

A task definition can be the target of several transitions.

- `join_mode = all` — the task is created only when **every** predecessor that
  can reach it has completed. Until then it exists in status `waiting`.
- `join_mode = any` — the first predecessor to complete creates it. Later ones
  do not create a duplicate.

Duplicate protection: the engine will not create a second open task for the same
`(instance_id, task_definition_id)` pair. This is enforced by a partial unique
index, not only by application code, because a double-clicked Complete button
otherwise creates two identical tasks in a queue.

---

## 7. Parallel branches

Two transitions from the same source create two tasks at once, in different
queues. Both appear immediately. Neither waits for the other.

This is how sample work and site inspection run at the same time.
`[CONFIRMED]` A7

---

## 8. Starting status of a new task

| Situation | Status |
|---|---|
| No unmet dependencies, department is open | `ready` — it appears in the queue |
| Waiting for a `join_mode = all` group | `waiting` — visible, not claimable |
| Waiting for something outside the system, e.g. the client's signature | `waiting` |
| Its project or site is currently blocked and this task is site work | `pending` — visible, greyed, not claimable |

`pending` versus `waiting` is a real distinction, not decoration. `waiting` means
"nothing is wrong, it is not your turn". `pending` means "something is wrong
elsewhere". They are shown differently and counted differently.

---

## 9. Blocking rules

Two independent kinds of block:

**Task-level block.** A person marks their own task blocked with a category and
a reason. `[CONFIRMED]` A16. It stops that task only.

**Site-level block.** A site is declared Not Ready. `[CONFIRMED]` A29:

- Every open or future task whose definition has
  `blocks_when_site_not_ready = true` moves to `pending` and cannot be claimed.
- Every other task, including all workshop preparation, continues normally.
- If `projects.block_all_when_site_not_ready` or the company setting is on,
  **all** tasks on the project are held instead. `[CONFIRMED]` A30
- A user with `site.override_block` can release a specific task, with a written
  reason. `[CONFIRMED]` A31

When a re-inspection returns Ready, all held tasks return to `ready`
automatically.

---

## 10. Deadlines

`DeadlineCalculator::for($taskDefinition, $project)`

Resolution order `[CONFIRMED]` A15:

1. `projects.sla_profile[task_definition.code]` if present
2. `workflow_task_definitions.sla_minutes`
3. no deadline

The result is then walked forward through the working calendar, so a 4-hour SLA
starting at 15:00 on a Thursday with a Friday weekend lands at 11:00 on Saturday,
not at 19:00 on Thursday. `[CONFIRMED]` A14

A user with `task.override_deadline` can change one task's `due_at`. The change
is audited with the old and new value.

**Overdue** is a computed flag `[PROPOSED]` B3. `RecalculateOverdueTasks` runs
every 10 minutes and sets `is_overdue` where `due_at` has passed and the status
is not `completed` or `cancelled`. A task can be `in_progress` and overdue at
the same time — that is exactly the situation a manager needs to see.

Escalation: when a task passes `escalate_after_minutes`, a second notification
goes to every supervisor of that department.

---

## 11. Manual intervention

Real operations need exceptions. Each one is permissioned and audited.

| Action | Permission | Effect |
|---|---|---|
| Reassign a task to another department | `task.reassign` | Queue changes, claim is released |
| Force-release someone else's claim | `task.reassign` | For absences |
| Cancel a task | `task.cancel` | Transitions from it never fire. If it was the only path forward, the instance is flagged as stalled on the admin screen |
| Create an ad-hoc task | `task.create_adhoc` | Belongs to the project but to no template. Never triggers transitions |
| Restart an instance | `workflow.manage` | Cancels open tasks and starts a fresh instance on the active template version |

A cancelled task never silently ends a project. The stalled-instance list in
`02-architecture.md` §9 is what catches it.

---

## 12. Phase 1 templates

| code | scope | Spec |
|---|---|---|
| `payment_cycle` | payment | `07-workflows/01-payment-to-accounting.md` |
| `sample_request` | sample | `07-workflows/02-sample-request-and-approval.md` |
| `sample_modification` | sample | `07-workflows/03-sample-modification.md` |
| `formula_registration` | sample | `07-workflows/04-formula-registration.md` |
| `site_visit` | site_visit | `07-workflows/05-site-visit-and-readiness.md` |

A project does not run one giant workflow. It runs several small ones, each
attached to the thing it is about. One `payment_cycle` instance per installment.
One `sample_request` instance per sample. This keeps every instance short enough
to reason about, and means a stuck sample never freezes a payment.

---

## 13. Test scenarios

Each is a feature test.

1. Completing a task with a missing required field is rejected and creates no
   successor.
2. Completing a task with a missing required attachment is rejected.
3. A successful completion creates exactly the expected successor tasks, in the
   expected departments, with the expected statuses.
4. Double-clicking Complete creates exactly one successor.
5. A conditional transition fires on `approved` and the alternative fires on
   `rejected`.
6. A `join_mode = all` target stays `waiting` until the last predecessor
   completes.
7. A `join_mode = any` target is created once, not twice.
8. Editing a published template creates a new version and running instances are
   unaffected.
9. A site declared Not Ready holds only site tasks; a workshop task on the same
   project is still claimable.
10. With `block_all_when_site_not_ready` on, the workshop task is held too.
11. An override with `site.override_block` releases one task, writes an audit row
    and produces a warning activity event.
12. Re-inspection returning Ready releases every held task.
13. A deadline crossing a Friday weekend and a holiday lands on the correct
    working hour.
14. A crash during transition evaluation rolls the completion back entirely.
