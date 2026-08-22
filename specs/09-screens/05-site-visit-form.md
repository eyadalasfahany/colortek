# Screen — Site Visit Form

**Route:** `/site-visits/{id}/edit`
**Who:** `site.visit_create`
**Data:** `GET /site-visits/{id}`, `GET /site-checklist-items`
**Source:** the real paper forms in `docs/`. See
`07-workflows/05-site-visit-and-readiness.md` for the full transcription.

This is the hardest screen in Phase 1 and the one most likely to be rejected by
its users. It is filled standing up, in a half-finished building, on a phone,
often with no signal, by an engineer who is used to a clipboard that works.

Design rule: **the digital form must be faster than the clipboard, or it will not
be used.**

---

## Two parts, matching the paper

A stepper with two steps, so the engineer is never scrolling through a hundred
fields:

```
[ 1 · Measurements ]   [ 2 · Site condition ]   → Review & submit
```

---

## Step 1 — Measurements

Reproduces page 1 of the paper form.

**Header** — prefilled from the project, all editable, because the real forms
carry client-facing values that differ from ours (`Omega-Mahmoud Eslily`,
`New Giza`, `SO 9577`):

date written, project engineer, project name, address, quotation number, and the
extra hand-written reference seen on the real forms (`BO397`).

**The table.** On desktop, a grid matching the paper column order. On mobile, a
list of rows with an "Add row" button, one row expanded at a time.

Per row:

| Field | Notes |
|---|---|
| Element | e.g. `Reception walls`. Leave blank to continue the element above — exactly how the paper form is used |
| Height | The boxed `Height 3.12` written beside the element name |
| Length, Width, Thickness, Diameter | Only length and width are used on most rows |
| Deductions | Zero or more. Each has a count, two dimensions, a `+`/`−` sign and a label |
| Other note | Free text, matching the أخرى column |
| Verified | The tick the engineers make by hand on paper |

**Deductions get a real input, not a text box.** On the paper form they are
written as `(2.54 × 1.80) − entrance Door` and `3 (0.69 × 0.68) − window`. If we
capture that as free text, Phase 2 cannot compute a net area and a year of site
data is wasted. The input is: `[count] ( [length] × [width] ) [− or +] [label]`
— which reads exactly like the handwriting it replaces.

**Additions exist.** One real form writes `4.97 × 1 +`. The sign control must
default to `−` but be easy to flip. `03-data-model.md` §9

**Area (m²)** is left blank on both real forms; the engineers write dimensions
only. So the column is shown, read-only, with a "calculated later" note, until
`[TBD]` C6 is answered. Do not force the engineer to compute something they have
never computed on site.

**Speed features that decide whether this screen survives:**

- The last entered element name is remembered, so a run of continuation rows is
  just two numbers each.
- Width persists between rows — the real forms show `2.67` repeated eleven times
  down a column. Retyping it eleven times on a phone would kill the screen.
- Numeric keypad on every dimension field.
- Add row is always reachable without scrolling.
- A running count: "17 rows · 2 pages".

---

## Step 2 — Site condition

Reproduces page 2. Five items only, in the paper's order, with the Arabic wording
taken from the form. `07-workflows/05` §1

1. **نسبة الرطوبة بالموقع** — humidity, a number with a `%` suffix + notes
2. **إخلاء الموقع من عُمال الغير** — نعم / لا + notes
3. **إخلاء الموقع من أى أثاث أو أدوات تعيق عمل فريق COLORTEK** — نعم / لا + notes
4. **توافر المعدات والخدمات اللازمة للعمالة من حيث مياه وكهرباء وسقالات** — نعم / لا + notes
5. **مدى تجهيز الموقع لبدء تنفيذ أعمال COLORTEK** — free text

Every item has its notes box, exactly as printed. Do not hide the notes behind
an "add note" link; on paper the box is always visible and engineers use it.

Yes/No are two large buttons, not a dropdown and not a toggle. Gloved thumb,
bright sunlight.

A "لا" on items 2, 3 or 4 marks the item as failing and shows, immediately:

> This will mark the site **Not Ready**. Site work will be held.
> Workshop preparation continues.

Telling the engineer the consequence at the moment they answer is what stops
surprise arguments later. `[CONFIRMED]` A29

Humidity above the configured maximum shows a warning and asks for confirmation,
but does not decide readiness. Item 5 is the engineer's verdict.
`[PROPOSED]` — no threshold exists on the paper form; ask before setting one.

---

## Review and submit

A summary: the row and page counts, the failed items, and the required signed
scan.

**Signed scan required.** The engineer photographs the signed paper form and
uploads it before submitting. `[PROPOSED]` — the paper form carries the client's
signature and remains the legal document; the digital record carries it, it does
not replace it.

Also captured: the client signatory's name.

On submit the visit locks. Editing afterwards needs `site.measurements_edit` and
is audited.

Submitting creates the readiness task. `07-workflows/05` §3.2

---

## Offline

The engineer will lose signal. This is not an edge case, it is Tuesday.

- The whole form is kept as a local draft, saved on every change.
- Photos are queued locally and uploaded when the connection returns.
- Measurements are pushed in **one bulk request**, not row by row.
  `08-api-contract.md` §7 — forty rows must not depend on forty successful
  requests.
- The screen states plainly whether the draft is saved locally or submitted to
  the server. An engineer must never believe a report was filed when it was not.
- A submitted-while-offline attempt is refused with a clear message and the draft
  is kept.

---

## States

| State | Behaviour |
|---|---|
| Loading | Skeleton, header prefilled from the project |
| Draft | Editable. "Saved on this device HH:MM" |
| Uploading | A progress row per queued photo |
| Submitted | Read-only, with a Print button that reproduces the paper form |
| Re-inspection | Opens prefilled with the previous visit's measurements, since the building has not changed — only the condition answers usually have |
| No permission | 404 |

The re-inspection prefill is the difference between a five-minute re-inspection
and a forty-minute one.
