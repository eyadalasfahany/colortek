/**
 * Colortek Design System — Tailwind theme extension
 * Pairs with design-system/tokens.css (the source of truth — this file
 * just re-exposes those CSS variables as Tailwind utility names).
 *
 * Usage in tailwind.config.ts:
 *
 *   import { colortekTheme } from "./design-system/tailwind.tokens";
 *
 *   export default {
 *     theme: {
 *       extend: colortekTheme,
 *     },
 *   };
 *
 * Requires design-system/tokens.css to be imported globally (e.g. in
 * app/globals.css) so the var(--…) references resolve at runtime.
 * This keeps ONE source of truth: change a hex in tokens.css and every
 * Tailwind class (bg-orange, text-status-danger, etc.) updates with it.
 */

export const colortekTheme = {
  colors: {
    neutral: {
      900: "var(--color-neutral-900)",
      800: "var(--color-neutral-800)",
      700: "var(--color-neutral-700)",
      600: "var(--color-neutral-600)",
      500: "var(--color-neutral-500)",
      400: "var(--color-neutral-400)",
      300: "var(--color-neutral-300)",
      200: "var(--color-neutral-200)",
      100: "var(--color-neutral-100)",
      50: "var(--color-neutral-50)",
    },
    orange: {
      DEFAULT: "var(--color-orange)",
      dark: "var(--color-orange-dark)",
      tint: "var(--color-orange-tint)",
    },
    spectrum: {
      green: "var(--color-green)",
      "green-bright": "var(--color-green-bright)",
      blue: "var(--color-blue)",
      "blue-bright": "var(--color-blue-bright)",
      indigo: "var(--color-indigo)",
      purple: "var(--color-purple)",
    },
    status: {
      neutral: "var(--color-status-neutral)",
      info: "var(--color-status-info)",
      pending: "var(--color-status-pending)",
      success: "var(--color-status-success)",
      warning: "var(--color-status-warning)",
      danger: "var(--color-status-danger)",
    },
  },
  fontFamily: {
    sans: ["var(--font-montserrat)", "system-ui", "sans-serif"],
  },
  fontSize: {
    display: ["var(--font-size-display)", { lineHeight: "var(--line-height-display)", letterSpacing: "var(--letter-spacing-display)", fontWeight: "var(--font-weight-display)" }],
    h1: ["var(--font-size-h1)", { lineHeight: "var(--line-height-h1)", fontWeight: "var(--font-weight-h1)" }],
    h2: ["var(--font-size-h2)", { lineHeight: "var(--line-height-h2)", fontWeight: "var(--font-weight-h2)" }],
    h3: ["var(--font-size-h3)", { lineHeight: "var(--line-height-h3)", fontWeight: "var(--font-weight-h3)" }],
  },
  spacing: {
    1: "var(--space-1)",
    2: "var(--space-2)",
    3: "var(--space-3)",
    4: "var(--space-4)",
    6: "var(--space-6)",
    8: "var(--space-8)",
    12: "var(--space-12)",
    16: "var(--space-16)",
    24: "var(--space-24)",
  },
  borderRadius: {
    input: "var(--radius-input)",
    button: "var(--radius-button)",
    card: "var(--radius-card)",
    modal: "var(--radius-modal)",
  },
  boxShadow: {
    1: "var(--shadow-1)",
    2: "var(--shadow-2)",
    3: "var(--shadow-3)",
  },
} as const;
