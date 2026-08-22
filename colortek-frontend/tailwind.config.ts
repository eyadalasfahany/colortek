import type { Config } from "tailwindcss";
import { colortekTheme } from "./design-system/tailwind.tokens";

export default {
  content: [
    "./app/**/*.{js,ts,jsx,tsx,mdx}",
    "./components/**/*.{js,ts,jsx,tsx,mdx}",
  ],
  theme: {
    extend: colortekTheme,
  },
} satisfies Config;
