# 03 — Data Model

> **Correction.** Every `*_en` / `*_ar` column pair below is written that way for
> readability, but the implementation uses **translatable JSON columns** per the
> company Laravel standard — `name`, `title`, `label` — read through
> `getTranslations()`. Two exceptions keep separate columns on purpose:
> `activity_events.message_*` and the task title snapshot. See
> `15-engineering-standards.md` §A4.

MySQL 8. All tables use `bigint unsigned` auto-increment primary keys unless
stated. All tables carry `created_at` and `updated_at`. Tables that users can
remove carry `deleted_at` (soft delete).

Money is stored as `decimal(14,2)` with a separate `currency` char(3), default
`EGP`. Never use float for money.

All timestamps are stored in UTC. The application timezone is `Africa/Cairo`.

---

## 1. Overview by group

| Group | Tables |
|---|---|
| People | `users`, `departments`, `department_user`, `employees`, roles/permissions (spatie) |
| Clients and projects | `clients`, `projects`, `quotations`, `project_stage_history` |
| Money | `payments`, `journals`, `journal_payment` |
| Workflow | `workflow_templates`, `workflow_task_definitions`, `workflow_transitions`, `workflow_instances`, `workflow_transition_log` |
| Tasks | `tasks`, `task_dependencies`, `task_status_events`, `task_comments`, `task_field_values` |
| Time | `time_entries`, `crew_logs`, `crew_log_members` |
| Samples | `samples`, `sample_approvals`, `formulas` |
| Site | `site_visits`, `site_checklist_items`, `site_visit_answers`, `site_measurements`, `site_measurement_deductions`, `corrective_actions` |
| Shared | `attachments`, `activity_events`, `notifications`, `audit_logs`, `settings`, `holidays`, `blocker_categories`, `odoo_sync_log` |

---

## 2. People

### `departments`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint | |
| `code` | varchar(30) unique | `sales`, `reception`, `accounting`, `workshop`, `tinting`, `site`, `management`, `admin` |
| `name_en` | varchar(100) | |
| `name_ar` | varchar(100) | |
| `is_queue` | boolean | Whether tasks can be queued to it. Default true. |
| `active` | boolean | |

Departments are the destination of every task. `[CONFIRMED]` A10

### `users`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint | |
| `name` | varchar(150) | |
| `email` | varchar(190) unique | |
| `password` | varchar(255) | |
| `phone` | varchar(30) nullable | |
| `locale` | enum('en','ar') | Default `en`. Drives UI language and direction. |
| `primary_department_id` | fk departments nullable | Used to pick their default queue view |
| `active` | boolean | Deactivated users keep their history |
| `last_seen_at` | timestamp nullable | Drives "who is online" |

Roles and permissions come from `spatie/laravel-permission`
(`roles`, `permissions`, `model_has_roles`, `role_has_permissions`).

### `department_user`
Pivot. A user may belong to several departments. `[PROPOSED]` B9

| Column | Type |
|---|---|
| `user_id` | fk users |
| `department_id` | fk departments |
| `is_supervisor` | boolean |

`is_supervisor` matters: only a supervisor may log crew hours for other people.

### `employees`
Workers who do **not** log in. `[CONFIRMED]` A11 — they exist only so a
supervisor can name them in a crew log or a timer.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint | |
| `code` | varchar(30) unique | Company staff number |
| `name` | varchar(150) | |
| `department_id` | fk departments | |
| `user_id` | fk users nullable | Set only if this person also has a login |
| `active` | boolean | |
| `odoo_employee_id` | varchar(50) nullable | Reserved for Phase 2 |

Keeping `employees` separate from `users` is deliberate. Most workers will never
have accounts, and we still need their hours attributed to them by name.

---

## 3. Clients, projects, quotations

### `clients`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint | |
| `odoo_client_id` | varchar(50) nullable, indexed | Empty in Phase 1 |
| `name` | varchar(200) | |
| `contact_person` | varchar(150) nullable | |
| `phone` | varchar(30) nullable | |
| `email` | varchar(190) nullable | |
| `address` | text nullable | |
| `notes` | text nullable | |

### `quotations`
Phase 1 stores the header only. We do not reproduce Odoo's quotation lines.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint | |
| `number` | varchar(50) unique | The Odoo sales-order / quotation number, e.g. `SO9577`. Source of all record references. `[CONFIRMED]` A8 |
| `client_id` | fk clients | |
| `total_value` | decimal(14,2) nullable | |
| `currency` | char(3) default 'EGP' | |
| `status` | enum('draft','sent','accepted','locked','cancelled') | `locked` is set when Sales locks it after payment |
| `locked_at` | timestamp nullable | |
| `locked_by_user_id` | fk users nullable | |
| `odoo_quotation_id` | varchar(50) nullable | Phase 2 |

### `projects`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint | |
| `reference` | varchar(60) unique | Derived from `quotations.number`. `[CONFIRMED]` A8 |
| `name` | varchar(200) | |
| `client_id` | fk clients | |
| `quotation_id` | fk quotations nullable | Null while the project is still a Lead |
| `stage` | enum | See section 3.1 |
| `status` | enum('active','on_hold','cancelled','completed') | |
| `sales_user_id` | fk users | The salesperson who owns the client relationship |
| `responsible_user_id` | fk users nullable | Project manager once execution starts |
| `site_address` | text nullable | |
| `site_lat`,`site_lng` | decimal(10,7) nullable | For the site supervisor's map link |
| `start_date` | date nullable | |
| `target_date` | date nullable | |
| `completed_at` | timestamp nullable | |
| `completed_by_user_id` | fk users nullable | `[CONFIRMED]` A9 — manual close in Phase 1 |
| `completion_note` | text nullable | |
| `block_all_when_site_not_ready` | boolean default false | Per-project override of the company setting. `[CONFIRMED]` A30 |
| `sla_profile` | json nullable | Per-project deadline overrides. `[CONFIRMED]` A15 |

#### 3.1 Project stage enum `[CONFIRMED]` A7

`lead`, `quotation`, `payment`, `sample`, `site`, `production`, `execution`,
`delivery`, `completed`.

`sample` and `site` may be active at the same time. The `stage` column stores the
**furthest** stage reached; the live workflow view derives what is actually
running from open tasks, not from this column. This avoids the classic bug where
one column tries to describe two parallel truths.

### `project_stage_history`
| Column | Type |
|---|---|
| `id`, `project_id` | |
| `from_stage`, `to_stage` | enum |
| `changed_by_user_id` | fk users nullable (null = system) |
| `reason` | text nullable |
| `created_at` | timestamp |

---

## 4. Money

### `payments`
One row per installment. `[CONFIRMED]` A17

| Column | Type | Notes |
|---|---|---|
| `id` | bigint | |
| `project_id` | fk projects | |
| `quotation_id` | fk quotations nullable | |
| `installment_number` | unsigned smallint | 1, 2, 3… |
| `amount` | decimal(14,2) | |
| `currency` | char(3) | |
| `method` | enum('bank_transfer','cash','cheque','other') | |
| `paid_at` | date | The date the client paid |
| `confirmed_by_user_id` | fk users nullable | Sales |
| `confirmed_at` | timestamp nullable | |
| `reviewed_by_user_id` | fk users nullable | Reception |
| `reviewed_at` | timestamp nullable | |
| `journal_id` | fk journals nullable | Set when it enters the day's journal |
| `status` | enum('pending_confirmation','confirmed','reviewed','journaled','accounted') | |
| `notes` | text nullable | |
| `odoo_payment_ref` | varchar(60) nullable | Phase 2 |

Payment proof is an `attachments` row of type `payment_proof`, and is
**required** before the Sales task can complete. `[CONFIRMED]` A19

### `journals`
One per calendar day for the whole company. `[CONFIRMED]` A20

| Column | Type | Notes |
|---|---|---|
| `id` | bigint | |
| `journal_date` | date unique | One per day, enforced by the unique index |
| `status` | enum('open','submitted','accounted') | |
| `prepared_by_user_id` | fk users nullable | Reception |
| `submitted_at` | timestamp nullable | |
| `accounted_by_user_id` | fk users nullable | Accounting |
| `accounted_at` | timestamp nullable | |
| `total_amount` | decimal(14,2) | Cached sum, recalculated on change |
| `odoo_journal_ref` | varchar(60) nullable | Phase 2 |

### `journal_payment`
Pivot with a snapshot of the amount at the time it entered the journal, so a
later correction to the payment does not silently change a submitted journal.

| Column | Type |
|---|---|
| `journal_id`, `payment_id` | |
| `amount_snapshot` | decimal(14,2) |

---

## 5. Workflow

This is the configurable heart. Full behaviour is in `05-workflow-engine.md`.

### `workflow_templates`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint | |
| `code` | varchar(50) | e.g. `payment_cycle`, `sample_request`, `site_visit` |
| `version` | unsigned int | `code` + `version` is unique |
| `name_en`, `name_ar` | varchar(150) | |
| `scope` | enum('project','sample','payment','site_visit') | What the instance attaches to |
| `is_active` | boolean | Only one active version per code |
| `published_at` | timestamp nullable | A draft cannot be instantiated |

`[PROPOSED]` B5 — editing a published template creates version N+1. Running
instances stay on their own version. This is the single most important rule in
the schema.

### `workflow_task_definitions`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint | |
| `template_id` | fk | |
| `code` | varchar(50) | Unique within the template, e.g. `sales_confirm_payment` |
| `title_en`, `title_ar` | varchar(200) | |
| `instructions_en`, `instructions_ar` | text nullable | Shown on the task card |
| `department_id` | fk departments | The queue it lands in |
| `is_entry_point` | boolean | Created when the instance starts |
| `sla_minutes` | unsigned int nullable | Working minutes. Defaults in `16-sla-defaults.md`, all admin-editable |
| `escalate_after_minutes` | unsigned int nullable | |
| `priority` | enum('low','normal','high','urgent') default 'normal' | |
| `requires_timer` | boolean | Workshop tasks true, office tasks false. `[CONFIRMED]` A12 |
| `required_fields` | json | Field keys that must be filled before completing |
| `required_attachment_types` | json | e.g. `["payment_proof"]` |
| `form_schema` | json | Describes the input form. See section 5.1 |
| `blocks_when_site_not_ready` | boolean | Marks this task as site work. `[CONFIRMED]` A29 |
| `auto_complete_rule` | json nullable | Rare: tasks the system can finish itself |

#### 5.1 `form_schema` shape `[PROPOSED]`

```json
{
  "fields": [
    { "key": "amount", "type": "money", "label_en": "Amount paid", "label_ar": "المبلغ المدفوع", "required": true },
    { "key": "method", "type": "select", "options": ["bank_transfer","cash","cheque"], "required": true },
    { "key": "notes", "type": "textarea", "required": false }
  ]
}
```

Types: `text`, `textarea`, `number`, `money`, `date`, `datetime`, `select`,
`multiselect`, `boolean`, `user`, `employee`, `file`.

The frontend renders the task form from this schema. That is what makes new task
types possible without frontend work.

### `workflow_transitions`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint | |
| `template_id` | fk | |
| `from_task_definition_id` | fk nullable | Null means "on instance start" |
| `to_task_definition_id` | fk | |
| `condition` | json nullable | See `05-workflow-engine.md` section 4 |
| `join_mode` | enum('all','any') default 'all' | If the target has several predecessors |
| `sort_order` | unsigned int | |

### `workflow_instances`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint | |
| `template_id` | fk | Points at a specific version |
| `subject_type`,`subject_id` | morphs | Project, Sample, Payment or SiteVisit |
| `project_id` | fk projects nullable, indexed | Denormalised for fast project queries |
| `status` | enum('running','completed','cancelled') | |
| `started_at`, `completed_at` | timestamp nullable | |

### `workflow_transition_log`
Every evaluation, taken or not. `[PROPOSED]` — see `02-architecture.md` §9.

| Column | Type |
|---|---|
| `id`, `instance_id`, `transition_id` |
| `source_task_id` | fk tasks nullable |
| `taken` | boolean |
| `reason` | varchar(255) nullable |
| `created_at` | timestamp |

---

## 6. Tasks

### `tasks`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint | |
| `reference` | varchar(60) unique | e.g. `SO9577-T012` |
| `instance_id` | fk workflow_instances | |
| `task_definition_id` | fk nullable | Null for manually created ad-hoc tasks |
| `project_id` | fk projects nullable, indexed | |
| `subject_type`,`subject_id` | morphs nullable | The sample / payment / site visit it concerns |
| `title` | varchar(200) | Copied from the definition at creation so later template edits do not rewrite history |
| `instructions` | text nullable | Copied likewise |
| `department_id` | fk departments | The queue. `[CONFIRMED]` A10 |
| `claimed_by_user_id` | fk users nullable | Set on claim, cleared on release |
| `claimed_at` | timestamp nullable | |
| `status` | enum | See `06-task-and-time-tracking.md` §1 |
| `priority` | enum('low','normal','high','urgent') | |
| `due_at` | timestamp nullable | Computed by `DeadlineCalculator` |
| `is_overdue` | boolean default false | Computed flag, refreshed by job. `[PROPOSED]` B3 |
| `escalated_at` | timestamp nullable | |
| `ready_at` | timestamp nullable | When dependencies cleared and it entered the queue |
| `started_at` | timestamp nullable | First time it went to `in_progress` |
| `completed_at` | timestamp nullable | |
| `completed_by_user_id` | fk users nullable | |
| `active_seconds` | unsigned int default 0 | Sum of running time, from `time_entries` |
| `paused_seconds` | unsigned int default 0 | |
| `blocked_seconds` | unsigned int default 0 | |
| `blocker_category_id` | fk blocker_categories nullable | |
| `blocker_reason` | text nullable | |
| `blocker_expected_resolution` | date nullable | |
| `blocked_by_user_id` | fk users nullable | |
| `cancelled_reason` | text nullable | |

Indexes: `(department_id, status)` for the queue screen,
`(project_id, status)`, `(claimed_by_user_id, status)` for "My Tasks",
`(status, due_at)` for the overdue job.

### `task_field_values`
The answers to the task's `form_schema`.

| Column | Type |
|---|---|
| `id`, `task_id` |
| `key` | varchar(60) |
| `value` | json |

Stored as rows rather than one json blob so we can query "every payment task
where method = cheque" later without scanning.

### `task_dependencies`
| Column | Type | Notes |
|---|---|---|
| `task_id` | fk | The dependent task |
| `depends_on_task_id` | fk | |
| `type` | enum('blocking','optional') | |

### `task_status_events`
Immutable log of every status change. Feeds the timeline on the task screen.

| Column | Type |
|---|---|
| `id`, `task_id` |
| `from_status`, `to_status` | varchar(20) |
| `user_id` | fk users nullable |
| `note` | text nullable |
| `created_at` | timestamp |

### `task_comments`
| Column | Type |
|---|---|
| `id`, `task_id`, `user_id` |
| `body` | text |
| `created_at` |

### `blocker_categories` `[CONFIRMED]` A16
| Column | Type |
|---|---|
| `id` | |
| `code` | varchar(40) unique |
| `name_en`, `name_ar` | varchar(120) |
| `requires_expected_date` | boolean |
| `notifies_department_id` | fk departments nullable |
| `active` | boolean |

Seeded with: `site_not_ready`, `missing_material`, `waiting_client`,
`technical_problem`.

`notifies_department_id` is what turns a blocker into action: "missing material"
notifies the warehouse, "site not ready" notifies the site team.

---

## 7. Time

### `time_entries` — workshop live timers `[CONFIRMED]` A12
| Column | Type | Notes |
|---|---|---|
| `id` | bigint | |
| `task_id` | fk tasks | |
| `user_id` | fk users | The supervisor running the timer |
| `employee_id` | fk employees nullable | The worker the time belongs to, if not the supervisor |
| `started_at` | timestamp | |
| `ended_at` | timestamp nullable | Null means running |
| `seconds` | unsigned int nullable | Written on stop |
| `source` | enum('timer','manual_correction','auto_closed') | |
| `needs_review` | boolean default false | Set by `CloseStaleTimers` |
| `note` | text nullable | Required when `source = manual_correction` |

Rule `[PROPOSED]` B10: a partial unique index ensures one user can only have one
row with `ended_at IS NULL` at a time.

### `crew_logs` — site end-of-day `[CONFIRMED]` A13
| Column | Type | Notes |
|---|---|---|
| `id` | bigint | |
| `project_id` | fk | |
| `log_date` | date | Unique with `project_id` |
| `supervisor_user_id` | fk users | |
| `task_id` | fk tasks nullable | The execution task it belongs to |
| `work_done` | text | What was achieved that day |
| `weather_note` | varchar(120) nullable | |
| `issues` | text nullable | |
| `status` | enum('draft','submitted') | |
| `submitted_at` | timestamp nullable | |

### `crew_log_members`
| Column | Type |
|---|---|
| `id`, `crew_log_id` |
| `employee_id` | fk employees |
| `hours` | decimal(5,2) |
| `role_note` | varchar(120) nullable |

Total workers for the day is a count of these rows. Total hours is their sum.
This is what feeds "how many people worked on this project".

### `holidays` `[CONFIRMED]` A14c
Managed by an admin through a screen, not seeded from a fixed list.
`09-screens/06-admin-calendar-and-holidays.md`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint | |
| `date` | date unique | |
| `name` | json | Translatable. `15-engineering-standards.md` §A4 |
| `type` | enum('public','company') | `[PROPOSED]` — tells a national holiday from a company shutdown |
| `is_recurring` | boolean default false | `[PROPOSED]` — fixed-date holidays only. Islamic holidays move each year and must be entered each year |
| `created_by_user_id` | fk users | |

Working hours live in `settings`: `work_start` = `09:00`, `work_end` = `17:00`
`[CONFIRMED]` A14b, `weekend_days` = `["friday"]` `[CONFIRMED]` A14.

One working day is therefore **8 hours**, which is the unit every SLA is
expressed in. `16-sla-defaults.md`

Changing a holiday or the shift hours recalculates the deadline of every open
task. See `09-screens/06-admin-calendar-and-holidays.md` §3 — this is not
optional; without it the calendar and the deadlines drift apart.

---

## 8. Samples

### `samples`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint | |
| `reference` | varchar(60) unique | `SO9577-S1`. `[PROPOSED]` B1 |
| `client_id` | fk clients | **Required.** `[CONFIRMED]` A21 |
| `project_id` | fk projects nullable | Optional. Attached later if the deal closes |
| `parent_sample_id` | fk samples nullable | Set when created by a modification. `[CONFIRMED]` A24 |
| `root_sample_id` | fk samples | Points at the first sample in the chain. Equals `id` for an original. Makes chain queries a single index lookup instead of a recursive walk |
| `attempt_number` | unsigned smallint | 1 for the original, then 2, 3… within the chain. `[PROPOSED]` B2 |
| `requested_by_user_id` | fk users | Sales |
| `requested_at` | timestamp | |
| `needed_by` | date nullable | |
| `color` | varchar(120) | Client requirement |
| `texture` | varchar(120) nullable | Client requirement |
| `client_reference` | varchar(200) nullable | The client's own reference, colour code or photo |
| `size` | varchar(60) nullable | Standard size |
| `finish_requirement` | text nullable | |
| `notes` | text nullable | |
| `modification_reason` | text nullable | Required when `parent_sample_id` is set |
| `status` | enum | See below |
| `approved_formula_id` | fk formulas nullable | The formula that was finally accepted |
| `is_presale` | boolean | True when `project_id` is null at creation. `[CONFIRMED]` A21 |

**Sample status:** `draft`, `pending_manager_approval`, `rejected_by_manager`,
`in_workshop`, `awaiting_formula_registration`, `ready_for_client_approval`,
`approved`, `rejected_by_client`, `superseded`, `cancelled`.

`superseded` is set on the parent when a modification creates a child. It keeps
the old record readable but out of active lists.

### `sample_approvals`
Both the manager approval and the client approval. `[CONFIRMED]` A22, A23

| Column | Type | Notes |
|---|---|---|
| `id`, `sample_id` | | |
| `type` | enum('manager','client') | |
| `decision` | enum('approved','rejected') | |
| `decided_by_user_id` | fk users nullable | The Approver, for manager type |
| `client_signatory_name` | varchar(150) nullable | For client type — the person who signed the printed form |
| `decided_at` | timestamp | For a client approval this is the date on the signed form, not the upload time |
| `recorded_by_user_id` | fk users | Who entered it into the system |
| `comments` | text nullable | Required when rejected |
| `form_generated_at` | timestamp nullable | When the printable PDF was produced |

The signed scan is an `attachments` row of type `client_approval_form` and is
**required** to record a client decision.

### `formulas` `[CONFIRMED]` A25, A26, A27
| Column | Type | Notes |
|---|---|---|
| `id` | bigint | |
| `reference` | varchar(60) unique | `SO9577-S1-F1`. `[PROPOSED]` B1 |
| `sample_id` | fk samples | |
| `version` | unsigned smallint | Increments within a sample |
| `body` | text nullable | The free-text recipe as written by Tinting |
| `author_employee_id` | fk employees nullable | Tinting — who actually made it |
| `author_user_id` | fk users nullable | If the author has a login |
| `authored_at` | date | |
| `registered_by_user_id` | fk users | Reception. Deliberately separate from the author |
| `registered_at` | timestamp | |
| `status` | enum('draft','registered','approved','superseded') | |
| `notes` | text nullable | |

The scanned tinting sheet is an `attachments` row of type `formula_sheet`.
Either `body` or a `formula_sheet` attachment must be present. A formula record
with neither is not a formula.

---

## 9. Site

### `site_checklist_items` `[CONFIRMED]` A28
The definition of the condition checklist, transcribed from page 2 of the real
paper form (`docs/Site Visit Report - 2nd.pdf`). Seeded, editable by an admin.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint | |
| `code` | varchar(50) unique | |
| `label_en`, `label_ar` | varchar(250) | The Arabic label is the exact wording on the paper form |
| `answer_type` | enum('percentage','yes_no','text') | |
| `unit` | varchar(20) nullable | `%` for humidity |
| `is_readiness_critical` | boolean | A `no` here forces Not Ready |
| `allows_note` | boolean | Every item on the real form has a مالحظات (notes) box |
| `sort_order` | unsigned int | |
| `active` | boolean | |

**Seed — the actual form, in order:**

| # | code | Arabic label (as printed) | English | type | critical |
|---|---|---|---|---|---|
| 1 | `humidity` | نسبة الرطوبة بالموقع | Humidity level at the site | percentage `%` | no — see note |
| 2 | `site_clear_of_other_workers` | إخلاء الموقع من عُمال الغير | Site cleared of other contractors' workers | yes_no | yes |
| 3 | `site_clear_of_obstructions` | إخلاء الموقع من أى أثاث أو أدوات تعيق عمل فريق COLORTEK | Site cleared of furniture or tools blocking the Colortek team | yes_no | yes |
| 4 | `utilities_available` | توافر المعدات والخدمات اللازمة للعمالة من حيث مياه وكهرباء وسقالات | Water, electricity and scaffolding available for the workers | yes_no | yes |
| 5 | `overall_readiness` | مدى تجهيز الموقع لبدء تنفيذ أعمال COLORTEK | How ready the site is to begin Colortek works | text | — |

Note on humidity: the paper form records a number with no printed pass/fail
threshold. `[PROPOSED]` store a configurable `humidity_max` setting; above it the
system warns and asks the engineer to confirm, but does not force Not Ready by
itself. The engineer's answer to item 5 is the real verdict. Do not invent a
threshold — ask before setting the default.

### `site_visits`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint | |
| `reference` | varchar(60) unique | `SO9577-SV1` |
| `project_id` | fk projects | |
| `task_id` | fk tasks nullable | The task it was performed under |
| `visit_number` | unsigned smallint | 1 for first inspection, 2+ for re-inspections |
| `parent_visit_id` | fk site_visits nullable | Set for a re-inspection |
| `engineer_user_id` | fk users | مهندس المشروع — the project engineer named on the form |
| `project_name_on_form` | varchar(200) | إسم المشروع as written. Kept because the real forms use a client-facing project name that differs from ours, e.g. `Omega-Mahmoud Eslily` |
| `address_on_form` | varchar(250) nullable | العنوان, e.g. `New Giza` |
| `quotation_number_on_form` | varchar(50) nullable | رقم الفاتورة/عرض السعر, e.g. `SO9577` |
| `client_reference_note` | varchar(60) nullable | The extra handwritten reference seen on the real forms, e.g. `BO397` |
| `visited_on` | date | تحريراً في — the date written on the form |
| `readiness` | enum('pending','ready','not_ready') | |
| `general_notes` | text nullable | |
| `client_signatory_name` | varchar(150) nullable | توقيع العميل |
| `engineer_signed_at`, `client_signed_at` | timestamp nullable | Set when the signed scan is uploaded |
| `submitted_at` | timestamp nullable | Locked after submission |

The signed paper scan is an `attachments` row of type `site_report_signed`.
The digital record does not replace the signed paper; it carries it.

### `site_visit_answers`
The condition statement, page 2.

| Column | Type |
|---|---|
| `id`, `site_visit_id`, `checklist_item_id` |
| `answer_value` | json — `true/false`, a number, or text |
| `passed` | boolean nullable |
| `note` | text nullable — the مالحظات box |

### `site_measurements` — page 1 of the report `[CONFIRMED]` A28b
This table is the measurement sheet. In Phase 1 we only capture it. In Phase 2
it becomes the input to the quantity engine, so capture it properly now.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint | |
| `site_visit_id` | fk | |
| `page_number` | unsigned smallint | The real forms run to several pages, numbered by hand |
| `line_number` | unsigned smallint | 1–23, the printed `م` column |
| `element_name` | varchar(200) nullable | عناصر المبنى, e.g. `Reception walls`, `Reception ceiling`, `Stairs`. Blank on continuation lines that belong to the element above |
| `element_group_id` | fk site_measurements nullable | Points at the line that named the element, so continuation lines roll up |
| `height_m` | decimal(8,3) nullable | Written next to the element name on the real form, e.g. `Height 3.12` |
| `length_m` | decimal(8,3) nullable | الطول (م) |
| `width_m` | decimal(8,3) nullable | العرض (م) |
| `thickness_m` | decimal(8,3) nullable | السمك (م) |
| `diameter_m` | decimal(8,3) nullable | القطر (م) |
| `other_note` | varchar(250) nullable | أخرى — free text on the real form |
| `area_sqm` | decimal(10,3) nullable | المساحة (متر مربع). Left blank by the engineers on both supplied forms. `[TBD]` C6 — computed or typed |
| `verified` | boolean default false | The engineers tick each completed line by hand; we reproduce that tick |

### `site_measurement_deductions`
On the real forms, openings are written inline as `(2.54 × 1.80) − entrance Door`
and `3 (0.69 × 0.68) − window`. Deductions are a separate concept from the
measurement and must not be flattened into free text, or Phase 2 cannot compute
a net area.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint | |
| `measurement_id` | fk site_measurements | |
| `kind` | varchar(40) | `door`, `window`, `opening`, `other`. `[TBD]` C7 — the real forms use free text |
| `label` | varchar(120) nullable | As written, e.g. `entrance Door` |
| `count` | unsigned smallint default 1 | The leading multiplier, e.g. the `3` in `3 (0.69 × 0.68)` |
| `length_m`, `width_m` | decimal(8,3) | |
| `sign` | enum('subtract','add') default 'subtract' | The forms show both `−` and `+` |

`sign` exists because one of the supplied forms writes `4.97 × 1 +` — an
addition, not a deduction. Assuming everything is subtracted would be wrong.

### `corrective_actions`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint | |
| `site_visit_id` | fk | |
| `checklist_item_id` | fk nullable | Which failed item it addresses |
| `description` | text | |
| `responsible_party` | enum('client','contractor','other_trade','colortek') | |
| `responsible_user_id` | fk users nullable | If it is our work |
| `task_id` | fk tasks nullable | The task created to fix it |
| `due_date` | date nullable | |
| `status` | enum('open','in_progress','resolved','cancelled') | |
| `resolved_at` | timestamp nullable | |
| `resolution_note` | text nullable | |

Most corrective actions on this form will be the client's responsibility, not
ours — clearing furniture, removing other trades, providing electricity. The
`responsible_party` column is what makes that visible instead of leaving the
site team looking responsible for a delay they did not cause.

---

## 10. Shared

### `attachments`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint | |
| `attachable_type`,`attachable_id` | morphs | |
| `type` | varchar(50) | `payment_proof`, `formula_sheet`, `client_approval_form`, `site_report_signed`, `site_photo`, `sample_photo`, `general` |
| `disk`, `path` | varchar(255) | |
| `original_name` | varchar(255) | |
| `mime_type` | varchar(120) | |
| `size_bytes` | unsigned bigint | |
| `uploaded_by_user_id` | fk users | |
| `caption` | varchar(255) nullable | |

Typed attachments are what let the engine enforce
`required_attachment_types` before a task can complete.

### `activity_events` `[PROPOSED]` B8
The human-readable live feed. Never edited, never deleted.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint | Also the SSE event id |
| `project_id` | fk nullable, indexed | |
| `subject_type`,`subject_id` | morphs nullable | |
| `type` | varchar(60) | `task.created`, `task.completed`, `payment.confirmed`, `sample.approved`, `site.not_ready`, … |
| `severity` | enum('info','success','warning','blocker','approval') | Drives the colour in the feed |
| `actor_user_id` | fk users nullable | Null means the system did it |
| `department_id` | fk departments nullable | |
| `message_en`, `message_ar` | varchar(400) | Rendered at write time, so the feed never re-renders wrongly after a rename |
| `payload` | json nullable | Structured detail for the UI link |
| `visible_to_permission` | varchar(80) nullable | If set, only users with this permission receive it |
| `created_at` | timestamp | |

`visible_to_permission` is how the live stream stays safe. Cost events in Phase 2
will carry a permission and never reach a worker's screen.

### `notifications`
Laravel's standard `notifications` table, database channel only. `[CONFIRMED]` A33

### `audit_logs` `[PROPOSED]` B8
The legal record. Different from the activity feed: it stores values, not prose.

| Column | Type |
|---|---|
| `id` |
| `auditable_type`,`auditable_id` | morphs |
| `event` | varchar(40) — `created`, `updated`, `deleted`, `override` |
| `user_id` | fk users nullable |
| `old_values`, `new_values` | json nullable |
| `reason` | text nullable |
| `ip_address` | varchar(45) nullable |
| `created_at` | timestamp |

Audited by rule: formula changes, sample approvals, payment records, journal
submission, task reassignment, deadline overrides, site readiness decisions, any
permission override, project completion.

### `settings`
| Column | Type |
|---|---|
| `key` | varchar(80) primary |
| `value` | json |
| `group` | varchar(40) |

Seeded keys: `work_start`, `work_end`, `weekend_days`, `default_locale`,
`reference_prefix`, `block_all_when_site_not_ready`, `journal_auto_open`,
`timer_auto_close`, `overdue_check_interval`.

### `odoo_sync_log`
Records what would have been sent to Odoo in Phase 1. See `02-architecture.md` §5.

| Column | Type |
|---|---|
| `id`, `direction` ('push','pull') |
| `entity` | varchar(50) |
| `local_id` | bigint nullable |
| `payload` | json |
| `status` | enum('simulated','pending','sent','failed') |
| `response` | json nullable |
| `idempotency_key` | varchar(80) nullable, unique |
| `created_at` |

---

## 11. Reference number rules `[PROPOSED]` B1

All references derive from the Odoo sales-order / quotation number.
`[CONFIRMED]` A8

| Record | Pattern | Example |
|---|---|---|
| Project | the sales-order number, verbatim | `SO9577` |
| Task | `{project}-T{seq}` | `SO9577-T012` |
| Sample | `{project}-S{seq}` | `SO9577-S1` |
| Sample from modification | next `S` number, chain kept via `root_sample_id` | `SO9577-S2` |
| Formula | `{sample}-F{version}` | `SO9577-S1-F1` |
| Site visit | `{project}-SV{seq}` | `SO9577-SV1` |

The format `SO9577` is taken from the real signed forms in `docs/`. A plain
sequence with an `SO` prefix and no year component. Do not add a year.

A pre-sale sample has no project. It uses a client-based prefix instead:
`CL-{client_id}-S{seq}`. When the sample is later attached to a project, the
reference is **not** rewritten — the old reference stays valid and the project
link is simply filled in. Renumbering records after people have written them on
physical samples would cause real confusion in the workshop.
