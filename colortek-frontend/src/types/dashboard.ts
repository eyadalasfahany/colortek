import type { WorkflowNextAction } from "@/types/projects";

export interface DashboardKpi {
  key: string;
  label: string;
  count: number;
  filter_href: string;
}

export interface ControlRoomProject {
  id: number;
  reference: string;
  name: string;
  client_name?: string | null;
  sales_user?: string | null;
  stage: string;
  site_ready: boolean;
  next_action?: WorkflowNextAction | null;
}

export interface ControlRoomData {
  kpis: DashboardKpi[];
  active_projects: ControlRoomProject[];
  needs_attention: {
    blockers: Array<{ task_id: number; title: string; project_reference?: string | null }>;
    waiting_approval: unknown[];
    sites_not_ready: Array<{ project_id: number; reference: string; name: string; corrective_actions: unknown[] }>;
  };
}

export interface WorkshopDashboardData {
  samples_to_make: unknown[];
  in_progress: unknown[];
  formulas_to_author: unknown[];
  active_timers: unknown[];
  blocked: unknown[];
  ready_to_hand_back: unknown[];
  stub?: boolean;
}

export interface SiteDashboardData {
  active_sites: unknown[];
  awaiting_inspection: unknown[];
  not_ready: Array<{ id: number; reference: string; name: string }>;
  reinspection_due: unknown[];
  corrective_actions: unknown[];
  crew_logs_today: unknown[];
  not_yet_reported: unknown[];
  stub?: boolean;
}

export interface SamplesDashboardData {
  columns: Record<string, unknown[]>;
  stub?: boolean;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null;
}

export function isControlRoomData(value: unknown): value is ControlRoomData {
  return isRecord(value) && Array.isArray(value.kpis) && Array.isArray(value.active_projects);
}
