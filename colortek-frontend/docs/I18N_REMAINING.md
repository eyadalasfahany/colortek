# Remaining i18n work

Phase 1 minimum (done in this branch):

- Login language switch (ar/en) before authentication
- `Accept-Language` header on all API requests via axios interceptor
- `dir="auto"` / `dir="rtl"` on key operational screens (login, tasks, projects, journal, crew log, people-hours, activity)
- Document root `lang`/`dir` updated client-side via `LocaleProvider`

Not in scope for this pass (recommended next steps):

1. **next-intl `[locale]` route segment** — move app under `app/[locale]/` with middleware locale detection and message catalogs
2. **Translated UI strings** — replace hardcoded English in components with `t()` lookups; extract labels for nav, buttons, empty states, validation
3. **Server-side locale** — pass locale from cookie/header into RSC layouts instead of client-only `document.documentElement`
4. **User locale sync** — after login, align stored locale with `user.locale` from `/auth/me` and PATCH preference endpoint
5. **Arabic typography QA** — Montserrat + RTL layout review on forms, tables, and mobile bottom nav
6. **Date/number formatting** — use `@internationalized/date` or `Intl` with locale for Cairo timezone labels
