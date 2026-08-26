# Phase 1 i18n — complete

Full next-intl setup is in place for Colortek Phase 1:

- Routes under `src/app/[locale]/` with `en` | `ar`, always-prefix
- `src/i18n/routing.ts`, `request.ts`, `navigation.ts` + `src/middleware.ts`
- Mirrored `messages/en.json` and `messages/ar.json`
- CI check: `npm run check:i18n` / `prebuild` via `scripts/check-i18n-keys.mjs`
- Cairo + Montserrat in `design-system/fonts.ts`; Arabic uses Cairo
- Login language switch (pre-auth) navigates locale route + sets `NEXT_LOCALE` cookie
- Axios `Accept-Language` from active locale (path → cookie → storage)
- Logical CSS on Colortek-owned layout/nav/task/project components

Optional later polish (not Phase 1 blockers): user.locale sync after `/auth/me`, Arabic-Indic digit policy confirmation, lint rule banning directional Tailwind utilities.
