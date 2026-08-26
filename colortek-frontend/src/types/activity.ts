export type ActivitySeverity = "info" | "success" | "warning" | "blocker" | "approval";

export interface ActivityEvent {
  id: number;
  type: string;
  severity: ActivitySeverity;
  message: string;
  actor?: { id: number; name: string } | null;
  department?: { id: number; name: string } | null;
  project?: { id: number; reference: string; name: string } | null;
  link?: string | null;
  link_params?: Record<string, unknown> | null;
  created_at: string;
}

export interface ActivityListParams {
  page?: number;
  per_page?: number;
  project_id?: number;
  department_id?: number;
  severity?: string;
  since?: string;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null;
}

export function isActivityEvent(value: unknown): value is ActivityEvent {
  return (
    isRecord(value) &&
    typeof value.id === "number" &&
    typeof value.message === "string" &&
    typeof value.created_at === "string"
  );
}
