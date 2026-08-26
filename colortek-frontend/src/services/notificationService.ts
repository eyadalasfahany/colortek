import { axiosInstance } from "@/config/axios";
import { isAppNotification, type AppNotification } from "@/types/notifications";
import type { PaginatedResponse } from "@/types/api";

function isPaginatedNotifications(value: unknown): value is PaginatedResponse<AppNotification> {
  return (
    typeof value === "object" &&
    value !== null &&
    Array.isArray((value as PaginatedResponse<AppNotification>).data)
  );
}

export async function getNotifications(params?: {
  page?: number;
  per_page?: number;
}): Promise<PaginatedResponse<AppNotification>> {
  const response = await axiosInstance.get<unknown>("/notifications", { params });

  if (!isPaginatedNotifications(response)) {
    throw new Error("Invalid notifications response");
  }

  return {
    ...response,
    data: response.data.filter(isAppNotification),
  };
}

export async function getUnreadNotificationCount(): Promise<number> {
  const response = await axiosInstance.get<{ data: { count: number } }>(
    "/notifications/unread-count",
  );

  return response.data?.count ?? 0;
}

export async function markNotificationRead(id: string): Promise<void> {
  await axiosInstance.post(`/notifications/${id}/read`);
}

export async function markAllNotificationsRead(): Promise<void> {
  await axiosInstance.post("/notifications/read-all");
}
