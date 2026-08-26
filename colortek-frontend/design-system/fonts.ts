/**
 * Colortek Design System — font loading (Next.js)
 * Montserrat for Latin; Cairo for Arabic (similar weight/x-height).
 *
 * Usage in app/[locale]/layout.tsx:
 *
 *   import { cairo, montserrat } from "../../design-system/fonts";
 *
 *   <html className={cn(montserrat.variable, cairo.variable, locale === "ar" ? cairo.className : montserrat.className)}>
 *
 * next/font self-hosts at build time (no runtime Google Fonts request).
 */

import { Cairo, Montserrat } from "next/font/google";

export const montserrat = Montserrat({
  subsets: ["latin"],
  weight: ["400", "500", "600", "700", "800"],
  variable: "--font-montserrat",
  display: "swap",
});

export const cairo = Cairo({
  subsets: ["arabic", "latin"],
  weight: ["400", "500", "600", "700", "800"],
  variable: "--font-cairo",
  display: "swap",
});
