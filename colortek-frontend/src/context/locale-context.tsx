"use client";

import { usePathname, useRouter } from "@/i18n/navigation";
import { isLocale, type Locale } from "@/i18n/routing";
import {
  localeDirection,
  setStoredLocale,
  type AppLocale,
} from "@/lib/locale";
import { useLocale as useNextIntlLocale } from "next-intl";
import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  type ReactNode,
} from "react";

interface LocaleContextValue {
  locale: AppLocale;
  dir: "ltr" | "rtl";
  setLocale: (locale: AppLocale) => void;
}

const LocaleContext = createContext<LocaleContextValue | null>(null);

/**
 * Bridges next-intl's active locale with cookie/localStorage for axios Accept-Language
 * and for the pre-auth language switcher (which also navigates to the locale route).
 */
export function LocaleProvider({ children }: { children: ReactNode }) {
  const intlLocale = useNextIntlLocale();
  const locale: Locale = isLocale(intlLocale) ? intlLocale : "en";
  const router = useRouter();
  const pathname = usePathname();

  useEffect(() => {
    setStoredLocale(locale);
  }, [locale]);

  const setLocale = useCallback(
    (next: AppLocale) => {
      setStoredLocale(next);
      router.replace(pathname, { locale: next });
    },
    [pathname, router],
  );

  const dir = localeDirection(locale);
  const value = useMemo(() => ({ locale, dir, setLocale }), [dir, locale, setLocale]);

  return <LocaleContext.Provider value={value}>{children}</LocaleContext.Provider>;
}

export function useLocale(): LocaleContextValue {
  const context = useContext(LocaleContext);

  if (!context) {
    throw new Error("useLocale must be used within LocaleProvider");
  }

  return context;
}
