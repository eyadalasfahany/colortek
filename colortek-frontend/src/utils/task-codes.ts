import type { FormSchemaField, TaskDetail } from "@/types/api";
export function resolveTaskCode(task: TaskDetail): string | null {
  if (task.task_code) return task.task_code;
  const n = new Set((task.form_schema?.fields ?? []).map((f) => f.name));
  if (n.has("author_employee_id")) return "tinting_author_formula";
  if (n.has("confirm_matches_sheet")) return "reception_register_formula";
  if (n.has("client_signatory_name") && n.has("decided_at"))
    return "sales_get_client_decision";
  if (n.has("ready_for_registration")) return "workshop_make_sample";
  return null;
}
export function getDecidedAtFieldLabel(f: FormSchemaField) {
  return f.name === "decided_at" ? "Date on the form (not today)" : f.label;
}
