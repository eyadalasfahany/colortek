import { axiosInstance } from "@/config/axios";
import type { AppNotification } from "@/types/live";

interface Paginated<T> {
  data: T[];
}

export async function fetchNotifications(): Promise<AppNotification[]> {
  const res = await axiosInstance.get<Paginated<AppNotification>>("/notifications");
  return res.data;
}

export async function fetchUnreadCount(): Promise<number> {
  const res = await axiosInstance.get<{ data: { count: number } }>("/notifications/unread-count");
  return res.data.count;
}

export async function markNotificationRead(id: string): Promise<void> {
  await axiosInstance.post(`/notifications/${id}/read`);
}

export async function markAllNotificationsRead(): Promise<void> {
  await axiosInstance.post("/notifications/read-all");
}
