import { axiosInstance } from "@/config/axios";
import { unwrapData } from "@/types/api";
import {
  isControlRoomData,
  type ControlRoomData,
  type SamplesDashboardData,
  type SiteDashboardData,
  type WorkshopDashboardData,
} from "@/types/dashboard";

export async function getControlRoomDashboard(): Promise<ControlRoomData> {
  const data = unwrapData<unknown>(
    await axiosInstance.get("/dashboard/control-room"),
  );

  if (!isControlRoomData(data)) {
    throw new Error("Invalid control room response");
  }

  return data;
}

export async function getWorkshopDashboard(): Promise<WorkshopDashboardData> {
  return unwrapData<WorkshopDashboardData>(
    await axiosInstance.get("/dashboard/workshop"),
  );
}

export async function getSiteDashboard(): Promise<SiteDashboardData> {
  return unwrapData<SiteDashboardData>(
    await axiosInstance.get("/dashboard/site"),
  );
}

export async function getSamplesDashboard(): Promise<SamplesDashboardData> {
  return unwrapData<SamplesDashboardData>(
    await axiosInstance.get("/dashboard/samples"),
  );
}
