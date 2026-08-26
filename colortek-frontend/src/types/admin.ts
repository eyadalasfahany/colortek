export interface AdminSetting {
  key: string;
  value: string | number | boolean | string[];
  group: string;
}

export interface AdminHoliday {
  id: number;
  date: string;
  name: { en: string; ar?: string };
  type: string;
  is_recurring: boolean;
  created_by?: { id: number; name: string } | null;
}

export interface AdminRole {
  id: number;
  name: string;
  permissions_count: number;
  users_count: number;
  permissions?: string[];
  is_protected: boolean;
}

export interface PermissionGroup {
  group: string;
  permissions: Array<{ name: string; description: string; dangerous: boolean }>;
}

export interface AdminUser {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  locale: string;
  active: boolean;
  roles?: string[];
  departments?: Array<{ id: number; code: string; name: string; is_supervisor: boolean }>;
  primary_department_id: number | null;
  is_super_admin: boolean;
}

export interface AdminEmployee {
  id: number;
  name: string;
  code: string | null;
  department_id: number | null;
  user_id: number | null;
  active: boolean;
}

export interface WorkflowTemplate {
  id: number;
  code: string;
  version: number;
  name_en: string;
  name_ar: string;
  scope: string;
  is_active: boolean;
  is_draft: boolean;
  published_at: string | null;
  definitions?: WorkflowTaskDefinition[];
  transitions?: WorkflowTransition[];
}

export interface WorkflowTaskDefinition {
  id: number;
  code: string;
  title_en: string;
  title_ar: string;
  instructions_en: string | null;
  instructions_ar: string | null;
  sla_minutes: number;
  escalate_after_minutes: number | null;
  priority: string;
  department?: { id: number; code: string; name: string } | null;
}

export interface WorkflowTransition {
  id: number;
  from_code: string;
  to_code: string;
  condition: string | null;
}

export interface SiteChecklistItem {
  id: number;
  code: string;
  label_en: string;
  label_ar: string;
  answer_type: string;
  unit: string | null;
  is_readiness_critical: boolean;
  allows_note: boolean;
  sort_order: number;
  active: boolean;
}

export interface StalledInstance {
  id: number;
  template_code: string | null;
  template_version: number | null;
  project: { id: number; reference: string; name: string } | null;
  last_completed_task: { id: number; reference: string; title: string } | null;
  stalled_since: string | null;
}

export interface UnclaimedTask {
  id: number;
  reference: string;
  title: string;
  department: { id: number; code: string; name: string } | null;
  due_at: string | null;
  minutes_past_due: number;
  project: { id: number; reference: string; name: string } | null;
}

export interface FailedJob {
  id: number;
  uuid: string;
  connection: string;
  queue: string;
  exception: string;
  failed_at: string;
}

export interface CoverageGap {
  permission: string;
  description: string;
  holder_count: number;
}

export interface PaginatedMeta {
  current_page: number;
  per_page: number;
  total: number;
}

export interface PaginatedResponse<T> {
  data: T[];
  meta?: PaginatedMeta & Record<string, unknown>;
  links?: Record<string, string | null>;
}
