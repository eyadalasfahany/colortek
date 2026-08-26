import type { UserSummary } from "@/types/api";

export interface AuditLogEntry {
  id: number;
  event: string;
  auditable_type: string;
  auditable_id: number;
  user: UserSummary | null;
  old_values: Record<string, unknown> | null;
  new_values: Record<string, unknown> | null;
  reason: string | null;
  created_at: string | null;
}

export interface AuditLogFilters {
  event?: string;
  user_id?: number;
  auditable_type?: string;
  since?: string;
  until?: string;
  per_page?: number;
  page?: number;
}
