export interface ProjectSummary {
  id: number;
  reference: string;
  name: string;
  stage: string;
  status: string;
  site_ready: boolean;
  client_name?: string | null;
  sales_user?: { id: number; name: string } | null;
}

export interface ProjectDetail extends ProjectSummary {
  block_all_when_site_not_ready?: boolean;
  client?: { id: number; name: string } | null;
  quotation_id?: number | null;
  quotation?: {
    id: number;
    number: string;
    total_value: string;
    currency: string;
    status: string;
    locked_at?: string | null;
  } | null;
  created_at?: string | null;
  updated_at?: string | null;
}

/**
 * Shape returned by ProjectWorkflowService::workflow()["stages"] — a set of
 * booleans, not a single `state` string. The old `state` field never existed on
 * the wire, so every stage rendered as pending.
 */
export interface WorkflowStage {
  key: string;
  label: string;
  completed: boolean;
  active: boolean;
  blocked: boolean;
  configured: boolean;
}

/** Shape returned by ProjectWorkflowService::workflow()["next_action"]. */
export interface WorkflowNextAction {
  task_id: number;
  title: string;
  department: string;
  holder: string;
  status: string;
  is_overdue: boolean;
}

export interface ProjectWorkflow {
  stages: WorkflowStage[];
  next_action?: WorkflowNextAction | null;
  current_stage?: string | null;
}

export interface ProjectListParams {
  page?: number;
  per_page?: number;
  stage?: string;
  status?: string;
  blocked?: boolean;
  overdue?: boolean;
  q?: string;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null;
}

export function isProjectSummary(value: unknown): value is ProjectSummary {
  return (
    isRecord(value) &&
    typeof value.id === "number" &&
    typeof value.reference === "string" &&
    typeof value.name === "string"
  );
}

export function isProjectDetail(value: unknown): value is ProjectDetail {
  return isProjectSummary(value);
}

export function isProjectWorkflow(value: unknown): value is ProjectWorkflow {
  return isRecord(value) && Array.isArray(value.stages);
}
