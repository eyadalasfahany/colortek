import type { Metadata } from "next";
import { montserrat } from "../design-system/fonts";
import "./globals.css";

export const metadata: Metadata = {
  title: "Colortek",
  description: "Colortek live project workflow app",
};

export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    <html lang="en" className={`${montserrat.variable} h-full antialiased`}>
      <body className="min-h-full flex flex-col font-sans">{children}</body>
    </html>
  );
}
