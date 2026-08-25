import { axiosInstance } from "@/config/axios";
import type { ActivityEvent } from "@/types/live";

interface Paginated<T> {
  data: T[];
}

export async function fetchActivity(since?: number): Promise<ActivityEvent[]> {
  const res = await axiosInstance.get<Paginated<ActivityEvent>>("/activity", {
    params: since ? { since } : undefined,
  });
  return res.data;
}
