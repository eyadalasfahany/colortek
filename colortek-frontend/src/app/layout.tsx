import type { Metadata } from "next";
import "./globals.css";
import "../../design-system/tokens.css";

export const metadata: Metadata = {
  title: {
    template: "%s | Colortek Dashboard",
    default: "Colortek Dashboard",
  },
  description: "Colortek admin dashboard.",
};

/**
 * Root layout stays minimal — html/body, lang, dir, and fonts live in [locale]/layout.
 * Next.js still requires a root layout file.
 */
export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return children;
}
