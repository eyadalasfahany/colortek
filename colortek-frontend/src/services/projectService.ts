import { axiosInstance } from "@/config/axios";
import {
  isProjectDetail,
  isProjectSummary,
  isProjectWorkflow,
  type ProjectDetail,
  type ProjectListParams,
  type ProjectSummary,
  type ProjectWorkflow,
} from "@/types/projects";
import { isPaginatedTasks, unwrapData, type PaginatedResponse } from "@/types/api";

function isPaginatedProjects(value: unknown): value is PaginatedResponse<ProjectSummary> {
  return (
    typeof value === "object" &&
    value !== null &&
    Array.isArray((value as PaginatedResponse<ProjectSummary>).data)
  );
}

export async function getProjects(
  params?: ProjectListParams,
): Promise<PaginatedResponse<ProjectSummary>> {
  const response = await axiosInstance.get<unknown>("/projects", {
    params: params as Record<string, string | number | boolean | undefined>,
  });

  if (!isPaginatedProjects(response)) {
    throw new Error("Invalid projects list response");
  }

  return response;
}

export async function getProjectByReference(reference: string): Promise<ProjectDetail> {
  const response = await axiosInstance.get<unknown>(
    `/projects/by-reference/${encodeURIComponent(reference)}`,
  );
  const data = unwrapData<unknown>(response);

  if (!isProjectDetail(data)) {
    throw new Error("Invalid project response");
  }

  return data;
}

export async function getProjectWorkflow(id: number): Promise<ProjectWorkflow> {
  const response = await axiosInstance.get<unknown>(`/projects/${id}/workflow`);
  const data = unwrapData<unknown>(response);

  if (!isProjectWorkflow(data)) {
    throw new Error("Invalid workflow response");
  }

  return data;
}

export async function getProjectTasks(
  id: number,
  params?: Record<string, string | number | boolean | undefined>,
) {
  const response = await axiosInstance.get<unknown>(`/projects/${id}/tasks`, { params });

  if (!isPaginatedTasks(response)) {
    throw new Error("Invalid project tasks response");
  }

  return response;
}

export async function getProjectPayments(id: number) {
  return unwrapData<unknown>(await axiosInstance.get(`/projects/${id}/payments`));
}

export async function getProjectActivity(
  id: number,
  params?: Record<string, string | number | boolean | undefined>,
) {
  const response = await axiosInstance.get<unknown>(`/projects/${id}/activity`, { params });
  return response;
}

export async function getProjectHours(id: number) {
  return unwrapData<unknown>(await axiosInstance.get(`/projects/${id}/hours`));
}

export function isProjectListItem(value: unknown): value is ProjectSummary {
  return isProjectSummary(value);
}
