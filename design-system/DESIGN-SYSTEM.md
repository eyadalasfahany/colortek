# Colortek Design System

Source: the Colortek logo (droplet mark) and the Neocement product catalogue
(dark charcoal pages, Montserrat, orange accent), plus the nine-state task
model from the AI-Powered Live Project Workflow & Cost Intelligence spec.

Visual reference (colors, type, components, workflow patterns, laid out on a
canvas): https://claude.ai/code/artifact/89f20793-fde9-4bc9-926e-8de014fbc3dd

Project stack: **Next.js**.

## Files in this folder

| File | What it's for |
|---|---|
| `tokens.css` | The source of truth. CSS custom properties for color, type scale, spacing, radius, shadow. Import once in `app/globals.css`. |
| `tailwind.tokens.ts` | Re-exposes `tokens.css` as a Tailwind theme extension (`bg-orange`, `text-status-danger`, `font-sans`, etc.), if this project uses Tailwind. |
| `fonts.ts` | `next/font/google` setup for Montserrat — self-hosted, no layout shift. |
| `logo.png` | The Colortek droplet mark, trimmed and web-sized. |
| `catalogue.pdf` | Original brand reference (Neocement catalogue) — background reading, not code. |

## How to wire it into the Next.js app

1. Copy this whole folder into the app repo, e.g. `src/design-system/`.
2. In `app/globals.css`, add at the top:
   ```css
   @import "../design-system/tokens.css";
   ```
3. In `app/layout.tsx`, load the font and put its CSS variable on `<html>`:
   ```tsx
   import { montserrat } from "../design-system/fonts";

   export default function RootLayout({ children }: { children: React.ReactNode }) {
     return (
       <html lang="en" className={montserrat.variable}>
         <body>{children}</body>
       </html>
     );
   }
   ```
4. If using Tailwind, extend the theme in `tailwind.config.ts`:
   ```ts
   import { colortekTheme } from "./src/design-system/tailwind.tokens";

   export default {
     theme: { extend: colortekTheme },
   };
   ```
5. Build components against the tokens, not raw hex values — `var(--color-orange)` / `bg-orange` / `text-status-danger`, never `#F26920` typed inline.

## Palette

**Neutrals** — `--color-neutral-{50…900}`, from `#F8F9F9` (Paper) to `#2A2F32` (Ink, the catalogue's signature dark surface).

**Brand accent** — `--color-orange` `#F26920` (primary action / links, the color at the tip of the droplet). Reserve it for the one most important thing on a screen — don't spread it across icons or secondary UI.

**Brand spectrum** — six hues lifted directly from the logo, reused for status/category instead of inventing a separate palette:
`--color-green #0E8340` · `--color-green-bright #45A840` · `--color-blue #155898` · `--color-blue-bright #3F77AB` · `--color-indigo #3F3E8D` · `--color-purple #7B5796`

## Task status → color (9-state model, from the workflow spec)

| Status | Color | Meaning |
|---|---|---|
| Not started | Neutral `#8B9295` | — |
| Ready | Indigo `#3F3E8D` | Queued, actionable |
| In progress | Blue `#155898` | Active |
| Paused | Blue bright `#3F77AB` | Active task, temporarily halted |
| Waiting | Purple `#7B5796` | Legitimately waiting on an input — not an error |
| Blocked | Danger `#C6392E` | Needs a human to resolve |
| Completed | Green `#0E8340` | — |
| Cancelled | Neutral `#AEB4B6`, strikethrough | — |
| Overdue | Orange `#F26920` | Past SLA |

`Waiting` and `Blocked` are deliberately different colors — per the spec, Waiting is a normal queue state and Blocked means something is actively wrong.

## Typography

Montserrat, weights 400/500/600/700/800. Scale: Display 64px/800 → H1 40px/800 → H2 28px/700 → H3 20px/700 → Body Large 17px → Body 15px → Body Small 13px → Label 12px/600/uppercase/tracked → Caption 11px/500/uppercase/tracked.

## Spacing / radius / elevation

4px base spacing scale (4 → 96). Radius is deliberately restrained and mostly square-edged: 2px inputs, 4px buttons, 8px cards, 12px modals. Shadows are soft and low-contrast; elevation on dark surfaces uses a border instead of a shadow.

## Component inventory (see the canvas for all states)

Buttons, form fields, status pills (all 9 states), tabs, project card, table/list row, app shell (sidebar + top bar), project stage tracker, AI insight card, live activity stream (severity-colored), confirmation dialog, empty state, task timer controls (Start/Pause/Block/Complete), KPI row, approval task card, blocked-task detail card, sample/formula revision trail, material planned-vs-actual table, cost summary (planned/actual/forecast).

## Notes for whoever (or whichever agent) builds from this

- Don't invent new colors — every hue here traces back to the logo, the catalogue, or a deliberate 1:1 mapping onto the workflow spec's status model. If a new use case needs a color, reuse one of the above before adding one.
- The spec (`AI-Powered Live Project Workflow & Cost Intelligence System.md`) marks several things TBD (labor cost formula, delivery workflow, exact SLAs, notification channels). Don't hardcode assumptions about those into the UI — build the configurable/placeholder version the spec asks for.
- Odoo is the system of record; this UI is the live workflow/orchestration layer on top of it — screens should read as "what's happening now and what's next," not as another ERP data-entry form.
