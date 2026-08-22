# Workflow 05 — Site Visit, Measurements and Readiness

**Template code:** `site_visit`
**Scope:** one instance per site visit, including re-inspections.
**Source documents:** `docs/Site Visit Report- 1st.pdf`,
`docs/Site Visit Report - 2nd.pdf`, and two real filled examples in
`docs/Site inspection & measurments.pdf`.

The digital form reproduces the paper form. It does not replace it with a
simplified version. `[CONFIRMED]` A28

---

## 1. What the real form actually is

The report the company uses today has two parts. Both are signed by the client
and by the project engineer, on **every page**.

### Part 1 — تقرير زيارة الموقع / Site Visit Report (measurements)

Header:

| Arabic on the form | English | Example from the real form |
|---|---|---|
| تحريراً في | Written on (date) | `2025/9/18` |
| مهندس المشروع | Project engineer | `Omega` |
| إسم المشروع | Project name | `Omega-Mahmoud Eslily` |
| العنوان | Address | `New Giza` |
| رقم الفاتورة/عرض السعر | Invoice / quotation number | `SO 9577` |

Then مساحات الموقع — site areas — a table of 23 numbered rows per page:

| Column | Arabic | Meaning |
|---|---|---|
| م | — | Line number, 1–23 |
| عناصر المبنى | Building elements | The zone, e.g. `Reception walls`, `Reception ceiling`, `Stairs` |
| المساحة (متر مربع) | Area (m²) | **Left blank on both real forms.** `[TBD]` C6 |
| الطول (م) | Length (m) | |
| العرض (م) | Width (m) | |
| السمك (م) | Thickness (m) | |
| القطر (م) | Diameter (m) | |
| أخرى | Other | Used for deductions and notes |

Signatures: توقيع العميل (client) and توقيع مهندس المشاريع (projects engineer).

**What the real forms show that no requirements document mentioned:**

1. Element names appear on the first row of a group only. The rows below belong
   to the same element until a new name appears.
2. Ceiling and wall heights are written next to the element name, boxed:
   `Height 3.12`, `H. 6.67`.
3. Openings are written inline in the أخرى column as expressions:
   `(2.54 × 1.80) − entrance Door`, `3 (0.69 × 0.68) − window`,
   `2 (0.69 × 0.68) −`.
4. Not everything is subtracted. One form writes `4.97 × 1 +` and `3 × 1 +`.
   **Additions exist.** Assuming a minus sign everywhere would produce wrong
   areas.
5. Reports run to several pages, numbered by hand at the bottom.
6. A second reference is sometimes written by hand in the margin — `BO397` on the
   real form. Probably a client order number. Captured as
   `client_reference_note`.
7. The engineer ticks each line by hand as it is verified.

This is why the measurement sheet is a proper table with a deductions child
table, and not a text box. `03-data-model.md` §9 defines `site_measurements` and
`site_measurement_deductions`.

### Part 2 — بيان بحالة الموقع / Statement of Site Condition

Only five items. The long checklist imagined in section 15 of the vision
document — lighting, protection, other trades, scaffolding as separate lines —
does **not** exist on the real form. Do not build it.

| # | Arabic (exact wording) | English | Type |
|---|---|---|---|
| 1 | نسبة الرطوبة بالموقع | Humidity level at the site | number, `%` |
| 2 | إخلاء الموقع من عُمال الغير | Site cleared of other contractors' workers | نعم / لا |
| 3 | إخلاء الموقع من أى أثاث أو أدوات تعيق عمل فريق COLORTEK | Site cleared of any furniture or tools blocking the Colortek team | نعم / لا |
| 4 | توافر المعدات والخدمات اللازمة للعمالة من حيث مياه وكهرباء وسقالات | Water, electricity and scaffolding available for the workers | نعم / لا |
| 5 | مدى تجهيز الموقع لبدء تنفيذ أعمال COLORTEK | How ready the site is to begin Colortek works | free text |

Every item has a مالحظات (notes) box under it. All five notes boxes must exist
in the digital form.

Items 2, 3 and 4 are `is_readiness_critical`. A "لا" on any of them means the
site is not ready.

Item 1, humidity, has **no printed threshold**. `[PROPOSED]`: store a
configurable `humidity_max`; above it the system warns the engineer and asks them
to confirm, but does not decide readiness by itself. Item 5 is the engineer's
verdict and it is what counts. Do not invent a humidity limit — ask before
setting the default.

---

## 2. The flow

```
[Site] Conduct the site visit
   │  fills measurements + condition statement, uploads the signed scan
   ▼
[Site] Set readiness
   ├─ Ready      ──▶ site tasks unlock, project may proceed
   └─ Not Ready  ──▶ corrective actions created
                     site tasks held  (workshop continues)
                        ▼
                  [various] Corrective actions
                        ▼
                  [Site] Re-inspection  → new site visit, visit_number + 1
```

---

## 3. Task definitions

### 3.1 `site_conduct_visit`

| | |
|---|---|
| Department | `site` |
| Permission | `site.visit_create`, `site.visit_submit` |
| Timer | no — a visit is a trip, not a timed task |
| SLA | `[PROPOSED]` `16-sla-defaults.md` — 3 working days from creation |
| Mobile | this is the most important mobile screen in the system |

**Form — header** (prefilled from the project, editable, because the real form
carries client-facing values that differ from ours):

`visited_on`, `engineer_user_id`, `project_name_on_form`, `address_on_form`,
`quotation_number_on_form`, `client_reference_note`.

**Form — measurements:** a repeating table. The mobile version adds one row at a
time; the desktop version is a grid. Per row: element name (optional,
continuation rows inherit), height, length, width, thickness, diameter, other
note, and any number of deduction lines with count, dimensions and a `+`/`−`
sign.

**Form — condition:** the five items above, each with its notes box.

**Attachments:** `site_report_signed` — the scan or photo of the signed paper
form. `[PROPOSED]` required to submit. The paper form is the legal document; the
digital record carries it, it does not replace it. Also `site_photo`, any number.

**Business rules:**

- On submit the visit is locked. Editing afterwards needs
  `site.measurements_edit` and is audited.
- `visit_number` = previous visits on this project + 1.
- A re-inspection sets `parent_visit_id`.

### 3.2 `site_set_readiness`

| | |
|---|---|
| Department | `site` |
| Permission | `site.set_readiness` |
| Timer | no |
| SLA | same working day as the visit |

**Form:**

| Key | Type | Required |
|---|---|---|
| `readiness` | select (`ready`, `not_ready`) | yes |
| `summary` | textarea | required when `not_ready` |

**Business rules:**

- Any critical item answered "no" forces `not_ready`. The engineer cannot
  override this on the form itself — overriding happens later, deliberately, with
  `site.override_block`. `[CONFIRMED]` A31
- `ready` → every held task on the project returns to `ready`; the project may
  proceed to `production` / `execution`.
- `not_ready` → the blocking rules in section 4, plus a corrective action task
  for each failed item.

### 3.3 `corrective_action_task`

One per failed critical item, created automatically.

| | |
|---|---|
| Department | depends on `responsible_party` |
| Permission | `site.corrective_action_manage` |

`responsible_party` decides the queue:

| Party | Goes to |
|---|---|
| `client` | Sales — they are the ones who talk to the client |
| `contractor` / `other_trade` | Sales, to raise with the client's main contractor |
| `colortek` | The relevant internal department |

On this particular form most corrective actions will be the client's: clearing
furniture, removing other trades, supplying electricity. Routing those to Sales
rather than to our site team is what keeps the delay attributed to the right
party in every report.

**Form:** `resolution_note` (required), optional photo.

### 3.4 `site_reinspection`

Created when all corrective actions on a visit are resolved. It starts a new
`site_visit` instance with `visit_number + 1` and `parent_visit_id` set.

---

## 4. Blocking `[CONFIRMED]` A29, A30, A31

When readiness is `not_ready`:

**Default — hold site work only.** Every task whose definition has
`blocks_when_site_not_ready = true` moves to `pending` and cannot be claimed.
Everything else, including all workshop preparation and tinting, continues
normally. `[CONFIRMED]` A29

**Optional — hold everything.** If `projects.block_all_when_site_not_ready` or
the company-wide setting is on, every open task on the project is held.
`[CONFIRMED]` A30

**Override.** A user with `site.override_block` can release one specific held
task. They must type a reason. The system writes an `audit_logs` row with
`event = 'override'` and a `warning` activity event that management sees in the
live feed. `[CONFIRMED]` A31

**Release.** When a re-inspection returns `ready`, every held task on the project
returns to `ready` automatically. Nobody has to unlock them by hand.

---

## 5. What Phase 1 does with the measurements

It stores them, shows them, and prints them. Nothing more. `[CONFIRMED]` A2

No area calculation, no material quantity, no cost. The `area_sqm` column exists
and stays empty until `[TBD]` C6 is answered.

This is deliberate. The measurement sheet is the input the Phase 2 quantity
engine will need, and capturing it structurally now means Phase 2 has a year of
real data to work from instead of starting empty. Capturing it as a photo would
have thrown that away.

What Phase 2 will need on top, recorded now so it is not forgotten:

- Which material goes on which element, and how many coats. `[TBD]` D14
- Whether area is computed or typed. `[TBD]` C6
- Whether deduction kinds are a fixed list. `[TBD]` C7

---

## 6. Transitions

| From | To | Condition |
|---|---|---|
| — | `site_conduct_visit` | — |
| `site_conduct_visit` | `site_set_readiness` | — |
| `site_set_readiness` | `corrective_action_task` (one per failed item) | `readiness = not_ready` |
| all `corrective_action_task` resolved | `site_reinspection` | `join_mode = all` |
| `site_set_readiness` | — (instance completes) | `readiness = ready` |

---

## 7. Test scenarios

1. All five condition items and their notes boxes exist and are saved.
2. A "no" on any critical item forces `not_ready`, whatever the engineer selects.
3. Humidity above the configured maximum warns but does not force `not_ready`.
4. A measurement row with a `+` deduction stores `sign = add`.
5. A deduction with a leading count, `3 (0.69 × 0.68)`, stores `count = 3`.
6. Continuation rows correctly roll up to the element named above them.
7. A report of 40 measurement rows across two pages saves and reprints in the
   right order with the right page numbers.
8. A visit cannot be submitted without the signed scan.
9. `not_ready` holds site tasks and leaves workshop tasks claimable.
10. With `block_all_when_site_not_ready` on, workshop tasks are held too.
11. An override releases exactly one task, writes an audit row and a warning
    event.
12. Re-inspection returning `ready` releases every held task automatically.
13. A corrective action whose party is `client` lands in the Sales queue.
14. The Arabic form renders right-to-left with the exact wording from the paper
    form.
