import { axiosInstance } from "@/config/axios";
import { unwrapData } from "@/types/api";

export interface OptionItem {
  id: number;
  label: string;
  code?: string | null;
}

function asOptions(payload: unknown): OptionItem[] {
  const data = unwrapData<unknown>(payload);
  if (!Array.isArray(data)) {
    return [];
  }

  return data.flatMap((row) => {
    if (!row || typeof row !== "object") {
      return [];
    }
    const record = row as Record<string, unknown>;
    const id = record.id;
    if (typeof id !== "number") {
      return [];
    }
    const label =
      (typeof record.label === "string" && record.label) ||
      (typeof record.name === "string" && record.name) ||
      (typeof record.reference === "string" && record.reference) ||
      String(id);
    const code = typeof record.code === "string" ? record.code : null;
    return [{ id, label, code }];
  });
}

export async function getProjectOptions(): Promise<OptionItem[]> {
  return asOptions(await axiosInstance.get<unknown>("/options/projects"));
}

export async function getDepartmentOptions(): Promise<OptionItem[]> {
  return asOptions(await axiosInstance.get<unknown>("/options/departments"));
}

export async function getEmployeeOptions(): Promise<OptionItem[]> {
  return asOptions(await axiosInstance.get<unknown>("/options/employees"));
}

export async function getUserOptions(departmentId?: number): Promise<OptionItem[]> {
  return asOptions(
    await axiosInstance.get<unknown>("/options/users", {
      params: departmentId ? { department_id: departmentId } : undefined,
    }),
  );
}

export async function getClientOptions(): Promise<OptionItem[]> {
  return asOptions(await axiosInstance.get<unknown>("/options/clients"));
}

export async function getBlockerCategoryOptions(): Promise<OptionItem[]> {
  return asOptions(
    await axiosInstance.get<unknown>("/options/blocker-categories"),
  );
}
