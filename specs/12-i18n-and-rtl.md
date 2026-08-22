# 12 — Arabic, English and RTL

`[CONFIRMED]` A32 — both languages, switchable by the user, with full RTL layout.
Designed in from the start.

Retrofitting RTL costs more than building it in. Every screen in `09-screens/`
must be built bidirectional from its first commit.

---

## 1. Who reads what

| Group | Likely language |
|---|---|
| Management, Sales, Accounting | English or Arabic, personal preference |
| Reception | Arabic |
| Workshop and tinting supervisors | Arabic |
| Site engineers | Arabic, and the site report is Arabic on paper |

The site visit form is the clearest case: the paper form is entirely in Arabic,
and the wording on screen must match the wording on paper or the engineers will
not trust it.

---

## 2. Backend

Per `15-engineering-standards.md` §A4, translatable content is stored in JSON
columns and read through `getTranslations()`.

| Content | Language source |
|---|---|
| Department names, task titles, instructions, blocker categories, checklist labels, holiday names | Translatable columns, both languages entered by an admin |
| Validation and error messages | Laravel translation files, `Accept-Language` header |
| Enum labels | `/enums/{name}`, localised server side |
| Activity feed messages | **Both** written at the moment of the event, into `message_en` and `message_ar` |
| User-entered content — notes, blocker reasons, formula text, site notes | Stored as typed. Never translated. Never transliterated |

The activity feed rule matters: rendering a template later would let a rename
rewrite history. `10-notifications-and-activity-stream.md` §1

User content is stored raw. A blocker reason typed in Arabic stays Arabic for an
English reader, because a machine translation of "the client's electrician did
not come" is worse than the original.

**Database:** `utf8mb4` with `utf8mb4_unicode_ci` on every text column. Arabic
in `utf8` rather than `utf8mb4` is how mixed Arabic-plus-emoji notes get
truncated.

---

## 3. Frontend

`next-intl`, per `15-engineering-standards.md` §B6.

- Routes under `src/app/[locale]/`, locales `en` and `ar`.
- `messages/en.json` and `messages/ar.json`, keys mirrored. A missing Arabic key
  fails the build. It must never fall back silently to English — a half-English
  Arabic screen is how a user decides the system is broken.
- The layout sets `dir` from the locale.
- The user's saved `users.locale` decides the default. The switcher is available
  before login too, so someone who cannot read English can still sign in.

### Layout rules

Use logical properties everywhere:

| Never | Always |
|---|---|
| `ml-4`, `pl-4` | `ms-4`, `ps-4` |
| `mr-4`, `pr-4` | `me-4`, `pe-4` |
| `text-left` | `text-start` |
| `text-right` | `text-end` |
| `left-0`, `right-0` | `start-0`, `end-0` |
| `border-l` | `border-s` |

An `ml-4` anywhere in the codebase is a bug in Arabic. This is worth a lint rule.

### Things that must **not** mirror

RTL mirrors the layout, not the world.

- **Numbers stay left to right.** `2.67`, `SO9577`, `50,000`.
- **Latin references stay as they are.** `SO9577-S1` reads the same in both
  languages, because it is written on the physical sample.
- **The measurement table keeps its physical meaning.** Length is length in both
  directions. The column *order* mirrors; the values do not.
- **Media controls, progress bars and timers** run in the reading direction of
  the interface, but a stopwatch counts up in both.
- **Logos and photographs** never flip.

### Formatting

Through next-intl, never by hand:

- Dates: `18 Sep 2025` / `١٨ سبتمبر ٢٠٢٥`
- Durations: "3 hours ago" / "منذ ٣ ساعات"
- Money: EGP with the correct symbol placement per locale

`[PROPOSED]` Arabic-Indic digits (`٠١٢٣`) for dates and durations in Arabic, but
**Western digits for measurements, references and money**. The real site forms
are filled in with Western digits, and a measurement sheet that reads `٢.٦٧` when
the paper says `2.67` will be misread. Confirm this with the client before
finalising.

---

## 4. Fonts

The design system uses Montserrat, which has no Arabic. An Arabic face is
required — Cairo, IBM Plex Sans Arabic or Noto Sans Arabic — chosen to sit at a
similar weight and x-height, self-hosted through `next/font`, with the fallback
stack set so nothing shifts on load.

This is a design-system change, not a page-level one. It belongs in
`design-system/fonts.ts` alongside the Montserrat setup. Arabic text set in a
fallback system font next to Montserrat looks broken, and the design system is
the only place to fix it once.

---

## 5. Testing

1. Every screen rendered in both locales in the component tests.
2. A lint rule banning directional Tailwind utilities.
3. A CI check that `en.json` and `ar.json` have identical key sets.
4. The site visit form checked against the printed paper form, item by item, by
   a native Arabic reader before it is shown to the site team.
5. Long-string testing: German-style overflow is not the risk here, but Arabic
   labels on the site form are long — item 3 is 62 characters. Buttons and table
   headers must be tested with the real strings, not with `Lorem`.

---

## 6. The printed documents

Two PDFs are generated by the system, and both are Arabic-first:

| Document | Spec |
|---|---|
| The sample client approval form | `07-workflows/02` §4 |
| The site visit report reprint | `09-screens/05` |

Both must reproduce the company's existing paper layout, right to left, with the
Colortek header. A generated document that looks unlike the paper it replaces
will simply be reprinted by hand, and the digital record will rot.
