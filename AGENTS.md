# Colortek

# caveman
- **caveman** (`~/.codex/plugins/cache/caveman/caveman/local/skills/caveman/SKILL.md`) - ultra-compressed communication mode. Trigger: `/caveman`, `use caveman`, or `caveman mode`.
- When the user asks for caveman, use the installed caveman skill before doing anything else. Caveman stays active until the user says `stop caveman`, `normal mode`, or `/caveman off`.

Before building any UI, read `design-system/DESIGN-SYSTEM.md` (also copied
into `colortek/design-system/`) and use its documented tokens — colors, type
scale, spacing, radius, shadow — instead of hardcoded hex values or ad-hoc
spacing. Don't invent new colors or sizes; everything needed is in
`tokens.css`.
