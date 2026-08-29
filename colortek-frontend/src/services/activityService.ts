import { axiosInstance } from "@/config/axios";
import {
  isActivityEvent,
  type ActivityEvent,
  type ActivityListParams,
} from "@/types/activity";
import type { PaginatedResponse } from "@/types/api";

function isPaginatedActivity(
  value: unknown,
): value is PaginatedResponse<ActivityEvent> {
  return (
    typeof value === "object" &&
    value !== null &&
    Array.isArray((value as PaginatedResponse<ActivityEvent>).data)
  );
}

export async function getActivityFeed(
  params?: ActivityListParams,
): Promise<PaginatedResponse<ActivityEvent>> {
  const response = await axiosInstance.get<unknown>("/activity", {
    params: params as Record<string, string | number | boolean | undefined>,
  });

  if (!isPaginatedActivity(response)) {
    throw new Error("Invalid activity response");
  }

  return {
    ...response,
    data: response.data.filter(isActivityEvent),
  };
}
