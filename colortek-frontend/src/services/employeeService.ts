import { axiosInstance } from "@/config/axios";
import { unwrapData } from "@/types/api";
import type { EmployeeSummary } from "@/types/samples";
export async function getEmployees(): Promise<EmployeeSummary[]> {
  const data = unwrapData<unknown>(await axiosInstance.get("/employees"));
  if (!Array.isArray(data)) throw new Error("Invalid employees");
  return data.filter(
    (v): v is EmployeeSummary =>
      typeof v === "object" &&
      v !== null &&
      typeof (v as EmployeeSummary).id === "number",
  );
}
