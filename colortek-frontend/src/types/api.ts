export type TaskStatus =
  | "ready"
  | "claimed"
  | "in_progress"
  | "paused"
  | "blocked"
  | "waiting"
  | "pending"
  | "completed";

export type TaskPriority = "low" | "normal" | "high" | "urgent";

export interface Department {
  id: number;
  code: string;
  name: string;
  is_queue: boolean;
  active: boolean;
}

export interface UserSummary {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  locale: string;
  active: boolean;
  permissions: string[];
  departments?: Department[];
  primary_department?: Department | null;
}

export interface TaskListItem {
  id: number;
  reference: string;
  title: string;
  status: TaskStatus;
  priority: TaskPriority;
  due_at: string | null;
  is_overdue: boolean;
  department: Department | null;
  claimant: UserSummary | null;
  project_id: number | null;
}

export interface FormSchemaField {
  name: string;
  type: string;
  label: string;
  required?: boolean;
  options?: Array<{ value: string; label: string }>;
}

export interface PaymentSubjectContext {
  type: "payment";
  id: number;
  installment_number: number;
  amount: string;
  currency: string;
  method: string;
  paid_at: string;
  status: string;
  notes?: string | null;
  project?: { id: number; reference: string; name: string } | null;
  client?: { id: number; name: string } | null;
  salesperson?: { id: number; name: string } | null;
  quotation?: { number: string; total_value: string; currency: string } | null;
  attachments?: PreviousOutputAttachment[];
}

export function isPaymentSubjectContext(value: unknown): value is PaymentSubjectContext {
  return isRecord(value) && value.type === "payment" && typeof value.id === "number";
}

export interface FormSchema {
  fields: FormSchemaField[];
}

export interface PreviousOutputAttachment {
  id: number;
  type: string;
  filename: string;
  url?: string;
}

export interface PreviousOutput {
  task_title?: string;
  task_code?: string;
  completed_by?: string;
  fields?: Record<string, unknown>;
  attachments?: PreviousOutputAttachment[];
}

export type TaskSubjectContext =
  | PaymentSubjectContext
  | import("@/types/samples").SampleSubjectContext
  | import("@/types/siteVisit").SiteVisitSubjectContext
  | import("@/types/siteVisit").CorrectiveActionSubjectContext;

export interface SiteBlockContext {
  visit_reference: string;
  visited_on: string;
  summary: string | null;
  failed_items: string[];
  open_corrective_count: number;
}

export interface TaskDetail extends TaskListItem {
  task_code?: string | null;
  subject?: TaskSubjectContext | null;
  site_block?: SiteBlockContext | null;
  instructions: string | null;
  claimed_at: string | null;
  started_at: string | null;
  completed_at: string | null;
  form_schema?: FormSchema | null;
  required_attachment_types?: string[];
  previous_outputs?: PreviousOutput[];
  project?: {
    id: number;
    reference: string;
    name: string;
    client_name?: string | null;
  } | null;
}

export interface CreatedTask {
  id: number;
  reference: string;
  title: string;
  department: string | null;
  status: TaskStatus;
  due_at: string | null;
}

export interface PaginatedMeta {
  current_page: number;
  per_page: number;
  total: number;
}

export interface PaginatedResponse<T> {
  data: T[];
  meta: PaginatedMeta;
  links?: {
    next: string | null;
    prev: string | null;
  };
}

export interface ApiEnvelope<T> {
  data: T;
  meta?: Record<string, unknown>;
}

export interface LoginResponse {
  token: string;
  user: UserSummary;
}

export interface CompleteTaskResponse {
  data: TaskDetail;
  meta: {
    created_tasks: CreatedTask[];
    project_stage?: string | null;
  };
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null;
}

export function isUserSummary(value: unknown): value is UserSummary {
  return (
    isRecord(value) &&
    typeof value.id === "number" &&
    typeof value.name === "string" &&
    typeof value.email === "string" &&
    Array.isArray(value.permissions)
  );
}

export function isLoginResponse(value: unknown): value is LoginResponse {
  return (
    isRecord(value) &&
    typeof value.token === "string" &&
    isUserSummary(value.user)
  );
}

export function isTaskListItem(value: unknown): value is TaskListItem {
  return (
    isRecord(value) &&
    typeof value.id === "number" &&
    typeof value.reference === "string" &&
    typeof value.title === "string" &&
    typeof value.status === "string"
  );
}

export function isTaskDetail(value: unknown): value is TaskDetail {
  return isTaskListItem(value);
}

export function isCreatedTask(value: unknown): value is CreatedTask {
  return (
    isRecord(value) &&
    typeof value.id === "number" &&
    typeof value.title === "string"
  );
}

export function isPaginatedTasks(value: unknown): value is PaginatedResponse<TaskListItem> {
  return (
    isRecord(value) &&
    Array.isArray(value.data) &&
    value.data.every(isTaskListItem) &&
    isRecord(value.meta) &&
    typeof value.meta.current_page === "number"
  );
}

export function isCompleteTaskResponse(value: unknown): value is CompleteTaskResponse {
  if (!isRecord(value) || !isTaskDetail(value.data) || !isRecord(value.meta)) {
    return false;
  }

  const createdTasks = value.meta.created_tasks;
  return Array.isArray(createdTasks) && createdTasks.every(isCreatedTask);
}

export function unwrapData<T>(payload: unknown): T {
  if (isRecord(payload) && "data" in payload) {
    return payload.data as T;
  }

  return payload as T;
}
