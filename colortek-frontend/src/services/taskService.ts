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

export async function getTasks(scope: TaskScope): Promise<PaginatedResponse<TaskListItem>> {
  const response = await axiosInstance.get<unknown>("/tasks", { params: { scope } });

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
  attachment_ids?: number[];
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
