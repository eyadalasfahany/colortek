import { axiosInstance } from "@/config/axios";
import {
  isCompleteTaskResponse,
  isPaginatedTasks,
  isTaskDetail,
  unwrapData,
  type CompleteTaskResponse,
  type PaginatedResponse,
  type TaskDetail,
  type TaskListItem,
} from "@/types/api";

export type TaskScope = "queue" | "my" | "all";

export interface TaskListParams {
  scope: TaskScope;
  project_id?: number;
  priority?: string;
  overdue?: boolean;
  per_page?: number;
}

export async function getTasks(params: TaskListParams): Promise<PaginatedResponse<TaskListItem>> {
  const response = await axiosInstance.get<unknown>("/tasks", {
    params: {
      scope: params.scope,
      project_id: params.project_id,
      priority: params.priority,
      overdue: params.overdue ? 1 : undefined,
      per_page: params.per_page,
    },
  });

  if (!isPaginatedTasks(response)) {
    throw new Error("Invalid tasks list response");
  }

  return response;
}

export async function getTask(id: number): Promise<TaskDetail> {
  const response = await axiosInstance.get<unknown>(`/tasks/${id}`);
  const data = unwrapData<unknown>(response);

  if (!isTaskDetail(data)) {
    throw new Error("Invalid task response");
  }

  return data;
}

export async function claimTask(id: number): Promise<TaskDetail> {
  const response = await axiosInstance.post<unknown>(`/tasks/${id}/claim`);
  const data = unwrapData<unknown>(response);

  if (!isTaskDetail(data)) {
    throw new Error("Invalid claim response");
  }

  return data;
}

export async function startTask(id: number): Promise<TaskDetail> {
  const response = await axiosInstance.post<unknown>(`/tasks/${id}/start`);
  const data = unwrapData<unknown>(response);

  if (!isTaskDetail(data)) {
    throw new Error("Invalid start response");
  }

  return data;
}

export interface CompleteTaskPayload {
  fields?: Record<string, unknown>;
  attachment_ids?: number[] | Record<string, number[]>;
}

export async function completeTask(
  id: number,
  payload: CompleteTaskPayload,
): Promise<CompleteTaskResponse> {
  const response = await axiosInstance.post<unknown>(`/tasks/${id}/complete`, payload);

  if (!isCompleteTaskResponse(response)) {
    throw new Error("Invalid complete response");
  }

  return response;
}

export async function overrideSiteBlock(id: number, reason: string): Promise<TaskDetail> {
  const response = await axiosInstance.post<unknown>(`/tasks/${id}/override-site-block`, { reason });
  const data = unwrapData<unknown>(response);

  if (!isTaskDetail(data)) {
    throw new Error("Invalid override response");
  }

  return data;
}

export async function releaseTask(id: number): Promise<TaskDetail> {
  const response = await axiosInstance.post<unknown>(`/tasks/${id}/release`);
  const data = unwrapData<unknown>(response);

  if (!isTaskDetail(data)) {
    throw new Error("Invalid release response");
  }

  return data;
}

export async function pauseTask(id: number): Promise<TaskDetail> {
  const response = await axiosInstance.post<unknown>(`/tasks/${id}/pause`);
  const data = unwrapData<unknown>(response);

  if (!isTaskDetail(data)) {
    throw new Error("Invalid pause response");
  }

  return data;
}

export async function resumeTask(id: number): Promise<TaskDetail> {
  const response = await axiosInstance.post<unknown>(`/tasks/${id}/resume`);
  const data = unwrapData<unknown>(response);

  if (!isTaskDetail(data)) {
    throw new Error("Invalid resume response");
  }

  return data;
}

export async function unblockTask(id: number): Promise<TaskDetail> {
  const response = await axiosInstance.post<unknown>(`/tasks/${id}/unblock`);
  const data = unwrapData<unknown>(response);

  if (!isTaskDetail(data)) {
    throw new Error("Invalid unblock response");
  }

  return data;
}

export interface BlockTaskPayload {
  blocker_category_id: number;
  reason: string;
  expected_resolution?: string;
}

export async function blockTask(id: number, payload: BlockTaskPayload): Promise<TaskDetail> {
  const response = await axiosInstance.post<unknown>(`/tasks/${id}/block`, payload);
  const data = unwrapData<unknown>(response);

  if (!isTaskDetail(data)) {
    throw new Error("Invalid block response");
  }

  return data;
}

export async function addTaskComment(id: number, body: string): Promise<void> {
  await axiosInstance.post(`/tasks/${id}/comments`, { body });
}
