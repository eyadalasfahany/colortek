import { axiosInstance } from "@/config/axios";
import type { PeopleHoursFilters, PeopleHoursReport } from "@/types/peopleHours";
import { unwrapData } from "@/types/api";

export async function getPeopleHours(
  filters: PeopleHoursFilters,
): Promise<PeopleHoursReport> {
  const res = await axiosInstance.get<unknown>("/people-hours", {
    params: {
      from: filters.from,
      to: filters.to,
      project_id: filters.project_id || undefined,
      department_id: filters.department_id || undefined,
      employee_id: filters.employee_id || undefined,
    },
  });
  return unwrapData<PeopleHoursReport>(res);
}
