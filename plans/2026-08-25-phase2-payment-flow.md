# Colortek Phase 1 — Payment Flow Vertical Slice — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Sales confirms a payment with proof → Reception reviews → daily journal → Accounting processes — visible in the Next.js app on Queue, My Tasks, and Task Detail. No WhatsApp forwarding.

**Depends on:** Plan 1 (spine) — merged in PR #1.

**Architecture:** Extend the Laravel API with money tables, `payment_cycle` workflow template, task-completion handlers keyed by `workflow_task_definitions.code`, attachment upload. Next.js app uses axios + React Query, permission-gated UI.

**Spec:** `specs/07-workflows/01-payment-to-accounting.md`, `specs/08-api-contract.md` §5–6 & §12, `specs/09-screens/02-task-detail.md`, `specs/03-data-model.md` §3–4, `specs/13-odoo-gateway-and-seed-data.md`, `specs/16-sla-defaults.md`

**Working directory:** `/workspace/colortek-api` (API), `/workspace/colortek-frontend` (UI)

---

## Task 1: Money and client data model

**Files:**
- Migrations: `clients`, `quotations`, `payments`, `journals`, `journal_payment`, `attachments`
- Models + factories for each
- Extend `Project` with relations to client, quotation, payments

**Check:** `php artisan migrate:fresh --seed` passes; factories create a project with client + quotation.

---

## Task 2: `payment_cycle` workflow template

**Files:**
- `database/seeders/PaymentWorkflowSeeder.php` (called from `ReferenceSeeder` or `DatabaseSeeder`)
- Task definitions: `sales_confirm_payment`, `reception_review_payment`, `sales_clarify_payment`, `reception_daily_journal`, `accounting_process_journal`, `reception_fix_journal`
- Transitions per `specs/07-workflows/01` §3
- SLAs from `specs/16-sla-defaults.md`

**Check:** `WorkflowTemplate::where('code','payment_cycle')->where('is_active',true)->exists()`

---

## Task 3: Task completion handlers

**Files:**
- `app/Services/Payments/PaymentTaskHandler.php` — dispatches by task definition code
- Hook from `TaskService::complete()` after validation, before/with engine advance
- Rules: proof required, quotation_locked, payment row status transitions, project stage, journal attach on `accepted`, query path creates clarify task via conditional transitions

**Check:** Feature tests 1–6 from `specs/07-workflows/01` §5

---

## Task 4: Attachments API

**Files:**
- `AttachmentController`, `AttachmentService`, `AttachmentResource`
- `POST /api/v1/attachments` (multipart), `GET /api/v1/attachments/{id}`
- `POST /api/v1/tasks/{id}/attachments` alias
- Store on local disk; validate mime/size

**Check:** Upload returns id; task complete accepts `attachment_ids.payment_proof: [id]`

---

## Task 5: Start payment + read APIs

**Files:**
- `PaymentController`, `JournalController`, `ProjectPaymentController`
- `POST /api/v1/projects/{id}/payments` — creates Payment + starts `payment_cycle` instance
- `GET /api/v1/payments`, `GET /api/v1/payments/{id}`
- `GET /api/v1/journals`, `GET /api/v1/journals/{date}`
- Extend `TaskResource` with `form_schema`, `required_attachment_types`, `previous_outputs`, subject context

**Check:** HTTP test: start payment → sales task in queue with form_schema

---

## Task 6: Daily journal job

**Files:**
- `app/Jobs/OpenDailyJournal.php`, schedule in `routes/console.php`
- Creates `journals` row + `reception_daily_journal` task if missing for today

**Check:** Job run creates one journal task per day

---

## Task 7: Payment flow feature tests

**Files:** `tests/Feature/PaymentFlowTest.php` — all 11 scenarios from spec §5

**Check:** `php artisan test --filter=PaymentFlowTest`

---

## Task 8: Frontend — auth and API layer

**Files:**
- `src/config/axios.ts`, `src/services/auth/`, `src/services/tasks/`
- `src/app/(without-layouts)/login/page.tsx`
- Token in memory + localStorage; auth guard on `(with-layouts)`

**Check:** Login → redirect to queue

---

## Task 9: Frontend — Queue and My Tasks

**Routes:** `/queue`, `/my-tasks`
**Spec:** `specs/09-screens/00-screen-map.md`

**Check:** Lists load from API with `scope=queue` / `scope=my`

---

## Task 10: Frontend — Task Detail

**Route:** `/tasks/[id]`
**Spec:** `specs/09-screens/02-task-detail.md`

Sections: header, instructions, previous outputs, dynamic form, attachments, actions, completion handover message.

**Check:** Complete sales payment → handover message names Reception task

---

## Definition of done

- [x] `composer qa` passes (API)
- [x] `PaymentFlowTest` — 11 scenarios green
- [ ] Demo: login as Sales → confirm payment with proof → Reception sees task in queue → complete review → journal → accounting
- [x] Frontend lint passes; Queue, My Tasks, Task Detail wired to API
