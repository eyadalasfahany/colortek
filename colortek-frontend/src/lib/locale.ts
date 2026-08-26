import { isLocale, type Locale } from "@/i18n/routing";

export type AppLocale = Locale;

const STORAGE_KEY = "colortek_locale";
const COOKIE_NAME = "NEXT_LOCALE";

export function getLocaleFromPathname(pathname: string): Locale | null {
  const match = pathname.match(/^\/(en|ar)(?=\/|$)/);
  if (match && isLocale(match[1])) {
    return match[1];
  }
  return null;
}

export function getStoredLocale(): Locale {
  if (typeof window === "undefined") {
    return "en";
  }

  const fromPath = getLocaleFromPathname(window.location.pathname);
  if (fromPath) {
    return fromPath;
  }

  const cookieMatch = document.cookie.match(
    new RegExp(`(?:^|; )${COOKIE_NAME}=(en|ar)(?:;|$)`),
  );
  if (cookieMatch && isLocale(cookieMatch[1])) {
    return cookieMatch[1];
  }

  const stored = window.localStorage.getItem(STORAGE_KEY);
  return stored && isLocale(stored) ? stored : "en";
}

export function setStoredLocale(locale: Locale): void {
  if (typeof window === "undefined") {
    return;
  }

  window.localStorage.setItem(STORAGE_KEY, locale);
  document.cookie = `${COOKIE_NAME}=${locale};path=/;max-age=31536000;SameSite=Lax`;
}

export function localeDirection(locale: Locale): "ltr" | "rtl" {
  return locale === "ar" ? "rtl" : "ltr";
}

export const LOCALE_LABELS: Record<Locale, string> = {
  en: "English",
  ar: "العربية",
};
