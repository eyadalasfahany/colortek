import { axiosInstance } from "@/config/axios";
import type { ActivityEvent, WorkflowStage } from "@/types/live";

export interface ProjectListItem {
  id: number;
  reference: string;
  name: string;
  stage: string;
  status: string;
  client?: { name: string } | null;
  sales_user?: { name: string } | null;
  site_ready?: boolean;
}

export interface ProjectDetail extends ProjectListItem {
  description?: string | null;
}

export interface ProjectWorkflow {
  stages: WorkflowStage[];
  next_action: {
    task_id: number;
    title: string;
    department: string;
    holder: string;
    status: string;
    is_overdue: boolean;
  } | null;
  current_stage: string;
}

export async function fetchProjects(params?: Record<string, string>): Promise<ProjectListItem[]> {
  const res = await axiosInstance.get<{ data: ProjectListItem[] }>("/projects", { params });
  return res.data;
}

export async function fetchProjectByReference(reference: string): Promise<ProjectDetail> {
  const res = await axiosInstance.get<{ data: ProjectDetail }>(`/projects/by-reference/${reference}`);
  return res.data;
}

export async function fetchProjectWorkflow(id: number): Promise<ProjectWorkflow> {
  const res = await axiosInstance.get<{ data: ProjectWorkflow }>(`/projects/${id}/workflow`);
  return res.data;
}

export async function fetchProjectActivity(id: number): Promise<ActivityEvent[]> {
  const res = await axiosInstance.get<{ data: ActivityEvent[] }>(`/projects/${id}/activity`);
  return res.data;
}
