import Providers from "@/app/providers";
import { cn } from "@/utils/cn";
import type { Metadata } from "next";
import { ThemeProvider } from "next-themes";
import { Toaster } from "sonner";
import { montserrat } from "../../design-system/fonts";
import "../../design-system/tokens.css";
import "./globals.css";

export const metadata: Metadata = {
  title: {
    template: "%s | Colortek Dashboard",
    default: "Colortek Dashboard",
  },
  description: "Colortek admin dashboard.",
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html
      suppressHydrationWarning
      lang="en"
      className={cn("h-full overflow-hidden antialiased", montserrat.variable, montserrat.className)}
    >
      <body className="h-full overflow-hidden bg-background-gray-secondary_alt_2">
        <ThemeProvider defaultTheme="light" enableSystem>
          <Providers>{children}</Providers>
        </ThemeProvider>
        <Toaster />
      </body>
    </html>
  );
}
