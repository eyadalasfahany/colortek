import { axiosInstance } from "@/config/axios";
import { unwrapData } from "@/types/api";

/**
 * Every API-facing enum is served by `GET /enums/{name}` as `{ value, label }`,
 * so no screen has to hardcode option lists that the backend already owns.
 */
export interface EnumOption {
  value: string;
  label: string;
}

export type EnumName =
  | "task_status"
  | "task_priority"
  | "blocker_category"
  | "payment_method"
  | "payment_status"
  | "journal_status"
  | "project_stage"
  | "project_status"
  | "quotation_status"
  | "sample_status"
  | "formula_status"
  | "approval_type"
  | "approval_decision"
  | "attachment_type"
  | "time_entry_source"
  | "site_readiness"
  | "corrective_action_status"
  | "responsible_party";

export async function getEnumOptions(name: EnumName): Promise<EnumOption[]> {
  const data = unwrapData<unknown>(
    await axiosInstance.get<unknown>(`/enums/${name}`),
  );

  if (!Array.isArray(data)) {
    return [];
  }

  return data.flatMap((row) => {
    if (!row || typeof row !== "object") {
      return [];
    }
    const record = row as Record<string, unknown>;
    const value = record.value ?? record.id;
    if (typeof value !== "string" && typeof value !== "number") {
      return [];
    }
    const label =
      (typeof record.label === "string" && record.label) ||
      (typeof record.name === "string" && record.name) ||
      String(value);

    return [{ value: String(value), label }];
  });
}
