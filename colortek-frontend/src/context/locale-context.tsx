"use client";

import {
  getStoredLocale,
  localeDirection,
  setStoredLocale,
  type AppLocale,
} from "@/lib/locale";
import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from "react";

interface LocaleContextValue {
  locale: AppLocale;
  dir: "ltr" | "rtl";
  setLocale: (locale: AppLocale) => void;
}

const LocaleContext = createContext<LocaleContextValue | null>(null);

export function LocaleProvider({ children }: { children: ReactNode }) {
  const [locale, setLocaleState] = useState<AppLocale>("en");

  useEffect(() => {
    setLocaleState(getStoredLocale());
  }, []);

  const setLocale = useCallback((next: AppLocale) => {
    setStoredLocale(next);
    setLocaleState(next);
  }, []);

  const dir = localeDirection(locale);

  useEffect(() => {
    document.documentElement.lang = locale;
    document.documentElement.dir = dir;
  }, [dir, locale]);

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
