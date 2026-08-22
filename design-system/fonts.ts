/**
 * Colortek Design System — font loading (Next.js)
 * Montserrat is the exact family used across the Neocement catalogue.
 *
 * Usage in app/layout.tsx:
 *
 *   import { montserrat } from "../design-system/fonts";
 *
 *   export default function RootLayout({ children }: { children: React.ReactNode }) {
 *     return (
 *       <html lang="en" className={montserrat.variable}>
 *         <body>{children}</body>
 *       </html>
 *     );
 *   }
 *
 * next/font self-hosts the font at build time (no runtime request to
 * Google Fonts, no layout shift) and exposes it as the CSS variable
 * --font-montserrat, which tailwind.tokens.ts already wires to
 * Tailwind's `font-sans`.
 */

import { Montserrat } from "next/font/google";

export const montserrat = Montserrat({
  subsets: ["latin"],
  weight: ["400", "500", "600", "700", "800"],
  variable: "--font-montserrat",
  display: "swap",
});
