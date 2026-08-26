export interface SiteChecklistItem {
  id: number;
  code: string;
  label_en: string;
  label_ar: string;
  answer_type: "percentage" | "yes_no" | "text";
  unit: string | null;
  is_readiness_critical: boolean;
  allows_note: boolean;
  sort_order: number;
}

export interface SiteMeasurementDeduction {
  count: number;
  length_m: number | null;
  width_m: number | null;
  sign: "subtract" | "add";
  label: string | null;
}

export interface SiteMeasurementRow {
  id?: number;
  page_number: number;
  line_number: number;
  element_name: string;
  height_m: string;
  length_m: string;
  width_m: string;
  thickness_m: string;
  diameter_m: string;
  other_note: string;
  area_sqm: number | null;
  verified: boolean;
  sort_order: number;
  deductions: SiteMeasurementDeduction[];
}

export interface SiteVisitAnswer {
  code: string;
  value: string | number | boolean | null;
  note: string;
}

export interface SiteVisitDetail {
  id: number;
  reference: string;
  project_id: number;
  visit_number: number;
  parent_visit_id: number | null;
  readiness: string;
  visited_on: string;
  project_name_on_form: string;
  address_on_form: string | null;
  quotation_number_on_form: string | null;
  client_reference_note: string | null;
  client_signatory_name: string | null;
  general_notes: string | null;
  submitted_at: string | null;
  is_submitted: boolean;
  engineer?: { id: number; name: string } | null;
  answers?: Array<{
    checklist_item: SiteChecklistItem;
    answer_value: { value: unknown };
    passed: boolean | null;
    note: string | null;
  }>;
  measurements?: SiteMeasurementRow[];
}

export interface SiteVisitSubjectContext {
  type: "site_visit";
  id: number;
  reference: string;
  visit_number: number;
  visited_on: string;
  readiness: string;
  is_submitted: boolean;
  project_name_on_form: string;
  address_on_form: string | null;
  quotation_number_on_form: string | null;
  client_reference_note: string | null;
  client_signatory_name: string | null;
  general_notes: string | null;
  measurement_count: number;
  has_critical_failures: boolean;
  open_corrective_count: number;
  conduct_task_id: number | null;
}

export interface CorrectiveActionSubjectContext {
  type: "corrective_action";
  id: number;
  description: string;
  responsible_party: string;
  status: string;
  visit_reference: string | null;
  checklist_label: string | null;
}

export interface SiteBlockContext {
  visit_reference: string;
  visited_on: string;
  summary: string | null;
  failed_items: string[];
  open_corrective_count: number;
}

export function isSiteVisitSubjectContext(value: unknown): value is SiteVisitSubjectContext {
  return typeof value === "object" && value !== null && (value as SiteVisitSubjectContext).type === "site_visit";
}

export function isCorrectiveActionSubjectContext(value: unknown): value is CorrectiveActionSubjectContext {
  return typeof value === "object" && value !== null && (value as CorrectiveActionSubjectContext).type === "corrective_action";
}
