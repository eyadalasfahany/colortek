import { format } from "date-fns";
import type { FormulaStatus, SampleStatus } from "@/types/samples";
type BadgeColor =
  | "gray"
  | "primary"
  | "error"
  | "warning"
  | "success"
  | "blue"
  | "purple"
  | "orange";
export function formatSampleStatusLabel(s: SampleStatus) {
  return s.replaceAll("_", " ");
}
export function sampleStatusBadgeColor(s: SampleStatus): BadgeColor {
  switch (s) {
    case "approved":
      return "success";
    case "rejected_by_client":
    case "rejected_by_manager":
    case "cancelled":
      return "error";
    case "in_workshop":
    case "awaiting_formula_registration":
      return "blue";
    case "ready_for_client_approval":
      return "purple";
    case "pending_manager_approval":
      return "warning";
    default:
      return "gray";
  }
}
export function formatFormulaStatusLabel(s: FormulaStatus) {
  return s.replaceAll("_", " ");
}
export function formatMinutesAsHours(m?: number) {
  if (!m) return "—";
  const h = Math.floor(m / 60),
    r = m % 60;
  return h ? (r ? `${h}h ${r}m` : `${h}h`) : `${r}m`;
}
export function formatSampleDate(v?: string | null) {
  return v ? format(new Date(v), "d MMM yyyy") : "—";
}
export const REPEAT_ATTEMPT_THRESHOLD = 4;
export function hasRepeatAttemptAlert(n: number) {
  return n >= REPEAT_ATTEMPT_THRESHOLD;
}
