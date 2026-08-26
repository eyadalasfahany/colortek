import Providers from "@/app/providers";
import { isLocale } from "@/i18n/routing";
import { cn } from "@/utils/cn";
import { NextIntlClientProvider } from "next-intl";
import { getMessages, setRequestLocale } from "next-intl/server";
import { ThemeProvider } from "next-themes";
import { notFound } from "next/navigation";
import { Toaster } from "sonner";
import { cairo, montserrat } from "../../../design-system/fonts";

type Props = {
  children: React.ReactNode;
  params: Promise<{ locale: string }>;
};

export function generateStaticParams() {
  return [{ locale: "en" }, { locale: "ar" }];
}

export default async function LocaleLayout({ children, params }: Props) {
  const { locale } = await params;
  if (!isLocale(locale)) {
    notFound();
  }

  setRequestLocale(locale);
  const messages = await getMessages();
  const dir = locale === "ar" ? "rtl" : "ltr";
  const fontClass = locale === "ar" ? cairo.className : montserrat.className;

  return (
    <html
      suppressHydrationWarning
      lang={locale}
      dir={dir}
      className={cn(
        "h-full overflow-hidden antialiased",
        montserrat.variable,
        cairo.variable,
        fontClass,
      )}
    >
      <body className="h-full overflow-hidden bg-background-gray-secondary_alt_2">
        <ThemeProvider defaultTheme="light" enableSystem>
          <NextIntlClientProvider locale={locale} messages={messages}>
            <Providers>{children}</Providers>
          </NextIntlClientProvider>
        </ThemeProvider>
        <Toaster />
      </body>
    </html>
  );
}
