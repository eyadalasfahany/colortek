/**
 * Turns a raw backend enum value into a display label: underscores become
 * spaces and the first letter is capitalised, so `in_progress` reads as
 * "In progress" rather than "in progress".
 *
 * Prefer a label from `GET /enums/{name}` when one is available — the backend
 * owns the wording and translates it. This is the fallback for values rendered
 * without a fetched catalogue.
 */
export function formatEnumLabel(value?: string | null): string {
  if (!value) {
    return "—";
  }

  const spaced = value.replaceAll("_", " ").trim();
  if (spaced === "") {
    return "—";
  }

  return spaced.charAt(0).toUpperCase() + spaced.slice(1);
}
