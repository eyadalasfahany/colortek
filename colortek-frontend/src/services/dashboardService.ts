import { axiosInstance } from "@/config/axios";
import type { ControlRoomData } from "@/types/live";

export async function getControlRoom(): Promise<ControlRoomData> {
  const res = await axiosInstance.get<{ data: ControlRoomData }>("/dashboard/control-room");
  return res.data;
}

export async function getWorkshopDashboard(): Promise<Record<string, unknown>> {
  const res = await axiosInstance.get<{ data: Record<string, unknown> }>("/dashboard/workshop");
  return res.data;
}

export async function getSiteDashboard(): Promise<Record<string, unknown>> {
  const res = await axiosInstance.get<{ data: Record<string, unknown> }>("/dashboard/site");
  return res.data;
}

export async function getSamplesDashboard(): Promise<Record<string, unknown>> {
  const res = await axiosInstance.get<{ data: Record<string, unknown> }>("/dashboard/samples");
  return res.data;
}
