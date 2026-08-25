import ar from "../../messages/ar.json";
import en from "../../messages/en.json";

const catalogs = { en, ar } as const;

export type Locale = keyof typeof catalogs;

export function getMessages(locale: string) {
  return catalogs[locale === "ar" ? "ar" : "en"];
}
