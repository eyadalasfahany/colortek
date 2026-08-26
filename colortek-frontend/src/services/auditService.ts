import { axiosInstance } from "@/config/axios";
import type { PaginatedResponse } from "@/types/admin";
import type { AuditLogEntry, AuditLogFilters } from "@/types/audit";

export async function getAuditLogs(
  filters: AuditLogFilters = {},
): Promise<PaginatedResponse<AuditLogEntry>> {
  return axiosInstance.get<PaginatedResponse<AuditLogEntry>>("/audit-logs", {
    params: {
      event: filters.event || undefined,
      user_id: filters.user_id || undefined,
      auditable_type: filters.auditable_type || undefined,
      since: filters.since || undefined,
      until: filters.until || undefined,
      per_page: filters.per_page ?? 25,
      page: filters.page,
    },
  });
}
