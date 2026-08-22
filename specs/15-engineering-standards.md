# 15 — Engineering Standards

This file binds the Colortek specs to the company's existing engineering
standards. Where anything earlier in this spec set disagrees with this file,
**this file wins**.

Sources:
- `.claude/skills/laravel-standards` — backend
- `.claude/skills/nextjs-standards` — frontend

---

# Part A — Laravel (backend)

## A1. The layer flow — non-negotiable

```
Form Request  →  thin Controller  →  Service  →  Repository  →  Resource
```

- **Business logic lives in Services.** Controllers only delegate and format.
- **Repositories own persistence.** Services never write queries inline.
- **Resources own output shape**, field by field.
- **No DTO or UseCase layers.** The company codebases do not use them; do not
  introduce them here.

This replaces the `app/Domain/...` layout sketched in `02-architecture.md` §3.
The domain services described there are real, but they live in `app/Services/`
and follow this shape.

## A2. File layout (non-modular — Colortek is a single app)

| Layer | Path |
|---|---|
| Model | `app/Models/{Entity}.php` |
| Repository | `app/Repositories/{Entity}Repository.php` |
| Service | `app/Services/{Entity}Service.php` |
| Controller | `app/Http/Controllers/Api/V1/{Entity}Controller.php` |
| Form Request | `app/Http/Requests/{Entity}Request.php` |
| Resource | `app/Http/Resources/{Entity}Resource.php` |
| Filter | `app/Http/Filters/{Entity}Filter.php` |
| Enum | `app/Enums/{Name}.php` |
| Policy | `app/Policies/{Entity}Policy.php` |
| Factory | `database/factories/{Entity}Factory.php` |
| Tests | `tests/Feature/`, `tests/Unit/` |

The workflow engine and the other orchestration classes are services:
`app/Services/Workflow/WorkflowEngine.php`,
`app/Services/Workflow/TransitionResolver.php`,
`app/Services/Tasks/TaskService.php`,
`app/Services/Time/TimerService.php`,
`app/Services/Samples/SampleService.php`,
`app/Services/Site/SiteVisitService.php`,
`app/Services/Payments/JournalService.php`.

Gateways stay where `02-architecture.md` §5 put them: `app/Gateways/Odoo/`.

## A3. Hard rules

| Rule | Applied here |
|---|---|
| `declare(strict_types=1);` | Every PHP file we own |
| Type everything | Params, returns, properties. No loose `array`/`mixed` across layers |
| Validation only in Form Requests | Never validate in a controller or a service |
| Thin controllers | No business `if`, no repository calls, no `validated()` parsing |
| Friendly 404 | `throw new ModelNotFoundException(__('Task not found'));` — never Laravel's default message |
| Explicit resources | Field by field. Never `$model->toArray()` |
| No timestamps in resources | No `created_at`/`updated_at`/`deleted_at` unless the contract needs them. Task timestamps (`due_at`, `started_at`, `completed_at`) are contract fields and stay |
| Pagination | Every list endpoint returns a paginator, `per_page` default 15. The service returns the paginator; the controller never paginates by hand |
| Options endpoints | Any entity used as a foreign key elsewhere exposes a light unpaginated id/name list |
| Enum catalogs | Every API-facing enum has a read endpoint returning `{ value, label }`. See A5 |
| Enums and value objects | No primitive obsession for status, money or ids |
| Domain exceptions | Explicit exception classes. Never `throw new \Exception()` |
| No N+1 | Every relation a resource touches is eager-loaded in the service or repository. Resources gate relations with `whenLoaded()`; the controller controls loading through a `relations` query parameter |
| Transactions | `DB::transaction()` around every multi-write operation |
| Soft-delete unique reuse | Unique rules ignore trashed rows (`,deleted_at,NULL`) and the service restores a trashed row instead of duplicating |
| Attach/detach for pivots | Pivot tables expose idempotent attach and body-based detach only — no full CRUD |

## A4. Translatable fields — correction to `03-data-model.md`

`03-data-model.md` used paired `name_en` / `name_ar` columns. **Replace them with
translatable JSON columns**, per the company standard.

Affected columns:

| Table | Column | Becomes |
|---|---|---|
| `departments` | `name_en`, `name_ar` | `name` json |
| `blocker_categories` | `name_en`, `name_ar` | `name` json |
| `workflow_templates` | `name_en`, `name_ar` | `name` json |
| `workflow_task_definitions` | `title_*`, `instructions_*` | `title` json, `instructions` json |
| `site_checklist_items` | `label_en`, `label_ar` | `label` json |
| `holidays` | `name_en`, `name_ar` | `name` json |

Resource variants, per the standard:

- `app/Http/Resources/Admin/{Entity}Resource.php` returns the full translation
  map via `getTranslations('name')` — the admin screens edit both languages.
- `app/Http/Resources/User/{Entity}Resource.php` returns the value resolved for
  the request locale — every operational screen.

Two exceptions that stay as separate columns, deliberately:

1. **`activity_events.message_en` / `message_ar`.** These are rendered prose,
   frozen at write time so history cannot be rewritten by a later rename.
   `10-notifications-and-activity-stream.md` §1. They are not translatable
   attributes; they are two finished strings.
2. **`tasks.title` and `tasks.instructions`**, copied from the definition at
   creation. They store the translation map as json, but they are a snapshot,
   never re-read from the definition. `05-workflow-engine.md` §5.

## A5. Enum catalog endpoints

Every enum the frontend renders gets `GET /api/v1/enums/{name}` returning
`{ value, label }` with the label in the request locale. The frontend never
hardcodes an option list.

Enums to expose: `task_status`, `task_priority`, `project_stage`,
`project_status`, `payment_method`, `payment_status`, `journal_status`,
`sample_status`, `formula_status`, `site_readiness`, `corrective_action_status`,
`responsible_party`, `approval_type`, `approval_decision`, `blocker_category`
(a table, exposed the same way), `attachment_type`, `time_entry_source`.

PHP enums live in `app/Enums/` and are backed by the same string values stored in
MySQL.

## A6. Options endpoints

| Endpoint | Used by |
|---|---|
| `GET /options/departments` | Task reassignment, admin |
| `GET /options/users` | Filters, responsible-user pickers |
| `GET /options/employees` | Crew log, timer attribution, formula author |
| `GET /options/clients` | Sample request, project creation |
| `GET /options/projects` | Attaching a pre-sale sample, filters |
| `GET /options/blocker-categories` | The block dialog |
| `GET /options/checklist-items` | The site visit form |

Unpaginated, id plus label, filtered by the caller's visibility.

## A7. Pagination — correction to `08-api-contract.md`

`08-api-contract.md` §1 showed a `meta` block. Confirmed, with the standard's
rules: every list endpoint returns a paginator with `per_page` defaulting to
**15**, the service returns the paginator, and the response envelope fills
`meta`. Only the `options` and `enums` endpoints return a plain collection.

## A8. Filters

Each list endpoint gets an `app/Http/Filters/{Entity}Filter.php`. The filters
already specified in `08-api-contract.md` §3–§7 are implemented there, not as
`if` chains in a controller. Every filterable and sortable column is indexed —
`03-data-model.md` §6 lists the task indexes.

## A9. Testing — non-negotiable

Pest. Every change ships tests.

- **Feature tests** cover HTTP contracts: boot context, authenticate, hit the
  endpoint, assert status plus database and JSON. The scenario lists at the end
  of each workflow spec in `07-workflows/` are the feature test suite.
- **Unit tests** cover services with Mockery-mocked repositories and gateways.
  Mock I/O — never mock the business rule under test. `WorkflowEngine`,
  `DeadlineCalculator`, `WorkingCalendar` and `TimerService` are the priority.
- `declare(strict_types=1);` in test files too.
- The soft-delete reuse regression is mandatory for any entity with a reusable
  unique key. Here that is `departments.code`, `blocker_categories.code`,
  `site_checklist_items.code` and `workflow_task_definitions.code`.

## A10. Before "done"

Run `php artisan test` and the project QA script (`composer qa` — Pint, PHPStan,
tests). Fix everything. Update the API collection on any endpoint change.

---

# Part B — Next.js (frontend)

## B1. Core principles

- **Server Components by default.** `'use client'` only at interactive leaves.
- **All data fetching goes through `src/services/`.** No component imports axios.
- **Client reads use `useQuery`, writes use `useMutation`.** Query keys live in
  `src/lib/queryKeys.ts` — never inline.
- **No `any`, no compiler-silencing `as`.** Narrow from `unknown` at the data
  boundary.
- **No hardcoded user-facing strings.** next-intl, keys mirrored across locales.
- **Cache first.** Opt out of caching only with a stated reason.
- **Reuse before creating.** Check `components/common/`, `utils/`, `lib/`,
  `hooks/` first.

Before writing any Next.js code, read the relevant doc under
`node_modules/next/dist/docs/`. Training data is out of date; the docs are the
source of truth.

## B2. Current state of `colortek-frontend/`

Already present: Next 16, React 19, TypeScript, Tailwind 4, TanStack Query,
TanStack Table, `src/app`, `src/services`, `src/components`, `src/hooks`,
`src/types`, `src/utils`, and the Colortek design system.

Missing and required for Phase 1:

| Missing | Needed for |
|---|---|
| `next-intl` and `messages/en.json`, `messages/ar.json` | `[CONFIRMED]` A32 — Arabic and English |
| The `src/app/[locale]/` segment | Same |
| `formik` and `yup` | Every form in the system |
| `axios` and `src/config/axios.ts` | The API client |
| `src/config/APIConfig.ts` (`serverPageFetchRequest`) | Server Component fetching |
| `src/config/clientAPI.ts` (`generalGetRequest`, `generalPostRequest`) | Client fetching |
| `src/lib/queryKeys.ts` | Every `useQuery` |
| `src/components/common/form/` | Shared Formik field components |

Routes move under `src/app/[locale]/`. This is a prerequisite for the RTL
requirement, not an optional refactor.

## B3. Structure

```text
src/
  app/[locale]/            routes; pages and layouts are Server Components
  app/api/                 route handlers — only for proxy, secrets, webhooks
  components/
    common/                shared generic components
    common/form/           shared Formik field components
    <Feature>/             feature components: Tasks/, Projects/, Samples/, Site/
  services/                every API call
  config/                  axios.ts, APIConfig.ts, clientAPI.ts
  lib/                     queryKeys.ts, business logic, Yup schema builders
  utils/                   small pure helpers
  hooks/                   custom hooks and Zustand stores
  types/                   shared API response types
  i18n/                    next-intl routing and request config
messages/                  en.json, ar.json — mirrored keys
```

## B4. Forms

Formik plus Yup, with the shared field components from
`components/common/form/`. Yup schemas are extracted into `src/lib/` and unit
tested for required, format and minimum cases.

This applies to every form in `09-screens/`, including the dynamic task form
rendered from `form_schema` (`03-data-model.md` §5.1). That renderer builds a
Yup schema from the schema's `required` flags and field types at runtime, in
`src/lib/taskFormSchema.ts`, and that builder is unit tested — it is the single
piece of frontend logic that every task in the system depends on.

## B5. Data fetching per screen

| Screen | Fetch |
|---|---|
| Control room | Server Component initial render, then `useQuery` + SSE for updates |
| Projects list, project detail | Server Component, `useQuery` for tab sections |
| My Tasks, Queue | `useQuery`, short `staleTime`, invalidated on any task mutation |
| Task detail | Server Component for the shell, `useMutation` for every action |
| Site visit form | Client — it holds a local offline draft. `09-screens/05` |
| Admin | `useQuery` throughout |

Every task action (`claim`, `start`, `complete`, `block`) is a `useMutation` that
invalidates the task and queue keys on success. The claim race
(`08-api-contract.md` §3) is handled in the mutation's `onError` for a 409, which
shows who took it and refetches the queue.

## B6. i18n and RTL — `[CONFIRMED]` A32

- next-intl with `en` and `ar`. Keys mirrored across both files; a missing Arabic
  key is a build-time failure, not a runtime fallback to English.
- `<html lang={locale} dir={locale === 'ar' ? 'rtl' : 'ltr'}>` in the locale
  layout.
- Tailwind logical properties throughout: `ms-*`, `me-*`, `ps-*`, `pe-*`,
  `text-start`, `text-end`. Never `ml-*`, `pl-*`, `text-left`.
- Numbers, dates and durations formatted through next-intl, not by hand.
- The site visit form's Arabic labels are the **exact wording from the paper
  form** and come from the API (`site_checklist_items.label`), not from
  `messages/ar.json`. They are data, not interface copy.
  `07-workflows/05-site-visit-and-readiness.md` §1

## B7. Other standards that apply

- **Images:** `<Image>` with the CDN Lambda loader and the centralised
  `getImageSizes()` presets. Site photos and sample photos are the main use.
- **Metadata:** every route exports metadata through `getMetadata`.
- **Performance:** heavy client libraries dynamically imported. The mobile task
  screen has a 300 KB budget (`02-architecture.md` §11), which means the map,
  the calendar and the charts must never load on it.
- **No committed `console.log`.**

## B8. Before "done"

```bash
npx tsc --noEmit     # zero errors
npm run build        # must succeed
```

Both must pass before any frontend work is reported complete. Never commit or
open a PR unless explicitly asked.

---

# Part C — What changed in the earlier spec files

Read these corrections alongside the originals.

| File | Correction |
|---|---|
| `02-architecture.md` §3 | The `app/Domain/` tree is replaced by the Service/Repository layout in A2. The classes and their responsibilities are unchanged |
| `03-data-model.md` | All `*_en` / `*_ar` column pairs become translatable json columns, except the two exceptions in A4 |
| `08-api-contract.md` §1 | Pagination follows A7: paginator on every list, `per_page` 15 |
| `08-api-contract.md` | Add the enum catalog endpoints (A5) and the options endpoints (A6) |
| `08-api-contract.md` | Every detail endpoint accepts a `relations` query parameter; resources gate with `whenLoaded()` |
| `09-screens/*` | Forms are Formik plus Yup with shared field components; all strings via next-intl |
