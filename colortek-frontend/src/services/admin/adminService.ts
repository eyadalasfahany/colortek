import { axiosInstance } from "@/config/axios";
import type {
  AdminEmployee,
  AdminHoliday,
  AdminRole,
  AdminSetting,
  AdminUser,
  CoverageGap,
  FailedJob,
  PaginatedResponse,
  PermissionGroup,
  SiteChecklistItem,
  StalledInstance,
  UnclaimedTask,
  WorkflowTemplate,
} from "@/types/admin";
import { unwrapData } from "@/types/api";

function listData<T>(payload: unknown): T[] {
  if (Array.isArray(payload)) return payload as T[];
  if (payload && typeof payload === "object" && "data" in payload) {
    const data = (payload as { data: unknown }).data;
    return Array.isArray(data) ? (data as T[]) : [];
  }
  return [];
}

export async function getAdminSettings(): Promise<AdminSetting[]> {
  const res = await axiosInstance.get<unknown>("/admin/settings");
  return listData<AdminSetting>(res);
}

export async function patchAdminSettings(body: Record<string, unknown>) {
  return axiosInstance.patch<{ data: AdminSetting[]; meta?: { affected_task_count: number } }>(
    "/admin/settings",
    body,
  );
}

export async function postCalendarImpact(body: Record<string, unknown>) {
  const res = await axiosInstance.post<{ data: { affected_task_count: number } }>(
    "/admin/calendar/impact",
    body,
  );
  return unwrapData<{ affected_task_count: number }>(res);
}

export async function getHolidays(): Promise<PaginatedResponse<AdminHoliday>> {
  return axiosInstance.get<PaginatedResponse<AdminHoliday>>("/admin/holidays");
}

export async function createHoliday(body: Record<string, unknown>) {
  return axiosInstance.post<{ data: AdminHoliday }>("/admin/holidays", body);
}

export async function updateHoliday(id: number, body: Record<string, unknown>) {
  return axiosInstance.patch<{ data: AdminHoliday }>(`/admin/holidays/${id}`, body);
}

export async function deleteHoliday(id: number, confirm = true) {
  return axiosInstance.delete<{ data: null }>(`/admin/holidays/${id}`, { params: { confirm } });
}

export async function deleteRole(id: number) {
  return axiosInstance.delete<{ data: unknown }>(`/admin/roles/${id}`);
}

export async function getRoles(): Promise<PaginatedResponse<AdminRole>> {
  return axiosInstance.get<PaginatedResponse<AdminRole>>("/admin/roles");
}

export async function getPermissions(): Promise<PermissionGroup[]> {
  const res = await axiosInstance.get<{ data: PermissionGroup[] }>("/admin/permissions");
  return unwrapData<PermissionGroup[]>(res);
}

export async function createRole(body: Record<string, unknown>) {
  return axiosInstance.post<{ data: AdminRole }>("/admin/roles", body);
}

export async function updateRole(id: number, body: Record<string, unknown>) {
  return axiosInstance.patch<{ data: AdminRole }>(`/admin/roles/${id}`, body);
}

export async function getUsers(): Promise<PaginatedResponse<AdminUser>> {
  return axiosInstance.get<PaginatedResponse<AdminUser>>("/admin/users");
}

export async function updateUser(id: number, body: Record<string, unknown>) {
  return axiosInstance.patch<{ data: AdminUser }>(`/admin/users/${id}`, body);
}

export async function syncUserRoles(id: number, roles: string[]) {
  return axiosInstance.post<{ data: AdminUser }>(`/admin/users/${id}/roles`, { roles });
}

export async function getEffectivePermissions(id: number) {
  const res = await axiosInstance.get<{ data: Array<{ permission: string; description: string }> }>(
    `/admin/users/${id}/effective-permissions`,
  );
  return unwrapData<Array<{ permission: string; description: string }>>(res);
}

export async function getEmployees(): Promise<PaginatedResponse<AdminEmployee>> {
  return axiosInstance.get<PaginatedResponse<AdminEmployee>>("/admin/employees");
}

export async function getCoverageGaps(): Promise<CoverageGap[]> {
  const res = await axiosInstance.get<{ data: CoverageGap[] }>("/admin/access/coverage");
  return unwrapData<CoverageGap[]>(res);
}

export async function getWorkflowTemplates(): Promise<PaginatedResponse<WorkflowTemplate>> {
  return axiosInstance.get<PaginatedResponse<WorkflowTemplate>>("/admin/workflow-templates");
}

export async function getWorkflowTemplate(id: number): Promise<WorkflowTemplate> {
  const res = await axiosInstance.get<{ data: WorkflowTemplate }>(
    `/admin/workflow-templates/${id}?relations=definitions,transitions`,
  );
  return unwrapData<WorkflowTemplate>(res);
}

export async function createWorkflowDraft(id: number) {
  return axiosInstance.post<{ data: WorkflowTemplate }>(`/admin/workflow-templates/${id}/draft`);
}

export async function publishWorkflowTemplate(id: number) {
  return axiosInstance.post<{ data: WorkflowTemplate }>(`/admin/workflow-templates/${id}/publish`);
}

export async function updateWorkflowTemplate(id: number, body: Record<string, unknown>) {
  return axiosInstance.patch<{ data: WorkflowTemplate }>(`/admin/workflow-templates/${id}`, body);
}

export async function getChecklistItems(): Promise<PaginatedResponse<SiteChecklistItem>> {
  return axiosInstance.get<PaginatedResponse<SiteChecklistItem>>("/admin/site-checklist-items");
}

export async function updateChecklistItem(id: number, body: Record<string, unknown>) {
  return axiosInstance.patch<{ data: SiteChecklistItem }>(`/admin/site-checklist-items/${id}`, body);
}

export async function getStalledInstances(): Promise<PaginatedResponse<StalledInstance>> {
  return axiosInstance.get<PaginatedResponse<StalledInstance>>("/admin/stalled-instances");
}

export async function getUnclaimedTasks(): Promise<PaginatedResponse<UnclaimedTask>> {
  return axiosInstance.get<PaginatedResponse<UnclaimedTask>>("/admin/unclaimed-tasks");
}

export async function getFailedJobs(): Promise<PaginatedResponse<FailedJob>> {
  return axiosInstance.get<PaginatedResponse<FailedJob>>("/admin/failed-jobs");
}

export async function retryFailedJob(uuid: string) {
  return axiosInstance.post<{ data: { retried: boolean } }>(`/admin/failed-jobs/${uuid}/retry`);
}
