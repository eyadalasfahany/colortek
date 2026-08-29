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
import {
  isPaginatedTasks,
  unwrapData,
  type PaginatedResponse,
} from "@/types/api";

function isPaginatedProjects(
  value: unknown,
): value is PaginatedResponse<ProjectSummary> {
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

export async function getProjectByReference(
  reference: string,
): Promise<ProjectDetail> {
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
  const response = await axiosInstance.get<unknown>(`/projects/${id}/tasks`, {
    params,
  });

  if (!isPaginatedTasks(response)) {
    throw new Error("Invalid project tasks response");
  }

  return response;
}

export async function getProjectPayments(id: number) {
  return unwrapData<unknown>(
    await axiosInstance.get(`/projects/${id}/payments`),
  );
}

export async function getProjectActivity(
  id: number,
  params?: Record<string, string | number | boolean | undefined>,
) {
  const response = await axiosInstance.get<unknown>(
    `/projects/${id}/activity`,
    { params },
  );
  return response;
}

export async function getProjectHours(id: number) {
  return unwrapData<unknown>(await axiosInstance.get(`/projects/${id}/hours`));
}

export function isProjectListItem(value: unknown): value is ProjectSummary {
  return isProjectSummary(value);
}

export interface CreateProjectPayload {
  name: string;
  client_id: number;
  reference?: string;
  quotation_id?: number | null;
  sales_user_id?: number | null;
}

export async function createProject(
  body: CreateProjectPayload,
): Promise<ProjectDetail> {
  const data = unwrapData<unknown>(
    await axiosInstance.post<unknown>("/projects", body),
  );

  if (!isProjectDetail(data)) {
    throw new Error("Invalid create project response");
  }

  return data;
}

export async function updateProject(
  id: number,
  body: {
    stage?: string;
    status?: string;
    name?: string;
    site_ready?: boolean;
    sales_user_id?: number | null;
    responsible_user_id?: number | null;
  },
): Promise<ProjectDetail> {
  const data = unwrapData<unknown>(
    await axiosInstance.patch<unknown>(`/projects/${id}`, body),
  );

  if (!isProjectDetail(data)) {
    throw new Error("Invalid update project response");
  }

  return data;
}

export async function getProjectSamples(id: number) {
  return axiosInstance.get<{ data: unknown[] }>(`/projects/${id}/samples`);
}

export async function getProjectSiteVisits(id: number) {
  return axiosInstance.get<{ data: unknown[] }>(`/projects/${id}/site-visits`);
}

/**
 * The three actions that actually start a workflow instance. Per
 * `specs/05-workflow-engine.md` §12 an instance is scoped to the thing it is
 * about — a payment installment, a sample, a site visit — not to the project
 * stage, so these are what create tasks.
 */
export async function startPayment(
  projectId: number,
  installmentNumber: number,
) {
  return axiosInstance.post<unknown>(`/projects/${projectId}/payments`, {
    installment_number: installmentNumber,
  });
}

export interface StartSamplePayload {
  client_id: number;
  project_id: number;
  color: string;
  texture?: string | null;
  size?: string | null;
  finish_requirement?: string | null;
  needed_by?: string | null;
  notes?: string | null;
}

export async function startSample(body: StartSamplePayload) {
  return axiosInstance.post<unknown>("/samples", body);
}

export async function startSiteVisit(projectId: number) {
  return axiosInstance.post<unknown>(`/projects/${projectId}/site-visits`, {});
}

export interface ProjectDocument {
  id: number;
  type: string | null;
  original_name: string;
  mime_type: string | null;
  size_bytes: number | null;
  caption?: string | null;
  source_type: string | null;
  source_id: number | null;
  uploaded_by?: { id: number; name: string } | null;
  created_at?: string | null;
}

export async function getProjectDocuments(id: number) {
  return axiosInstance.get<PaginatedResponse<ProjectDocument>>(
    `/projects/${id}/documents`,
    { params: { per_page: 100 } },
  );
}
