# 16 — Default Deadlines per Task Type

`[PROPOSED]` — every value here is our suggestion. All of them are editable by an
admin in the settings screen, so the client corrects them in the system rather
than in this document. Review before the pilot.

Resolution order at runtime `[CONFIRMED]` A15:

1. `projects.sla_profile[task_code]` — a per-project override
2. `workflow_task_definitions.sla_minutes` — the default below
3. no deadline

---

## The unit

The shift is **09:00 to 17:00**, Friday weekend, plus admin-managed holidays.
`[CONFIRMED]` A14, A14b, A14c

So **one working day = 8 hours**. Deadlines are stored in working minutes and
walked forward through the calendar, never in raw wall-clock time. A 4-hour SLA
starting at 15:00 on a Thursday is due at 11:00 on Saturday.
`06-task-and-time-tracking.md` §6

---

## Proposed defaults

| Task | Department | SLA | Escalate at | Reasoning |
|---|---|---|---|---|
| `sales_confirm_payment` | Sales | 4h | 8h | Sales already has the proof in hand; this is data entry |
| `reception_review_payment` | Reception | 4h | 8h | Must clear the same day to reach that day's journal |
| `sales_clarify_payment` | Sales | 4h | 1d | A query is already a delay; keep it short |
| `reception_daily_journal` | Reception | end of day | — | The client's existing process is end-of-day. `[CONFIRMED]` A20 |
| `accounting_process_journal` | Accounting | 1d (8h) | 2d | |
| `reception_fix_journal` | Reception | 4h | 1d | |
| `sales_create_sample_request` | Sales | none | — | The flow's entry point. A deadline here would be meaningless |
| `reception_review_sample_request` | Reception | 4h | 8h | Pure coordination |
| `sales_fix_sample_request` | Sales | 4h | 1d | |
| `manager_approve_sample` | Management | **4h** | 8h | The cheapest delay in the company to fix and the easiest to forget. Deliberately tight |
| `workshop_make_sample` | Workshop | **3d** (24h) | 5d | Physical work with drying time. The only value here that really needs the client's judgement |
| `tinting_author_formula` | Tinting | 1d (8h) | 2d | Runs in parallel with the workshop task |
| `reception_register_formula` | Reception | 4h | 8h | Transcription |
| `sales_get_client_decision` | Sales | **5d** (40h) | 10d | Depends on the client, not on us. Starts in `waiting`. The SLA measures our chasing, not their signing |
| `site_conduct_visit` | Site | 3d (24h) | 5d | Needs travel and client access |
| `site_set_readiness` | Site | 4h | 8h | Same day as the visit |
| `corrective_action_task` | varies | 3d (24h) | 5d | Most are the client's responsibility; we can only chase |
| `site_reinspection` | Site | 2d (16h) | 4d | Faster than a first visit — measurements prefill. `09-screens/05` |

---

## Two values worth arguing about

**`manager_approve_sample` at 4 hours.** An approval that takes three days holds
up the workshop, the formula, the client meeting and the project. It is one
person reading one screen. If 4 hours proves unrealistic, raise it — but raise it
deliberately, after seeing the real aging report, rather than starting loose.

**`sales_get_client_decision` at 5 days.** This one is not really ours. The
client signs when the client signs. The SLA exists so that a sample sitting
unsigned for three weeks appears somewhere, not so anyone is blamed at day six.
It starts in `waiting` status for that reason. `05-workflow-engine.md` §8

---

## What escalation does

At `escalate_after_minutes`, a second notification goes to every supervisor of
that department. It is a separate notification that says it is an escalation, not
a repeat of the first. `10-notifications-and-activity-stream.md` §2

Escalation never reassigns anything and never blocks anything. It makes a delay
visible to one more person.

---

## Reviewing these later

Once there are three months of real data, the aging report answers this properly:
the 80th percentile of actual completion time per task type is a better default
than any guess in this table. That report is in the Phase 2 list.
`14-phase-2-backlog.md` §9
