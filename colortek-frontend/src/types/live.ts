export type ActivitySeverity = "info" | "success" | "warning" | "blocker";

export interface ActivityEvent {
  id: number;
  type: string;
  severity: ActivitySeverity;
  message: string;
  actor?: { id: number; name: string } | null;
  department?: { id: number; name: string } | null;
  project?: { id: number; reference: string; name: string; stage?: string } | null;
  link?: string | null;
  link_params?: Record<string, unknown> | null;
  created_at: string;
}

export interface WorkflowStage {
  key: string;
  label: string;
  completed: boolean;
  active: boolean;
  blocked?: boolean;
  configured: boolean;
}

export interface ProjectCardData {
  id: number;
  reference: string;
  name: string;
  client_name?: string | null;
  sales_user?: string | null;
  stage?: string;
  site_ready?: boolean;
  next_action?: { title: string; task_id?: number } | null;
}

export interface ControlRoomData {
  kpis: Array<{ key: string; label: string; count: number; filter_href: string }>;
  active_projects: ProjectCardData[];
  needs_attention: {
    blockers: Array<{ task_id: number; title: string; project_reference?: string }>;
    waiting_approval: unknown[];
    sites_not_ready: Array<{ project_id: number; reference: string; name: string }>;
  };
}

export interface AppNotification {
  id: string;
  type: string;
  message: string;
  project_id?: number | null;
  project_reference?: string | null;
  link?: string | null;
  link_params?: Record<string, unknown> | null;
  read_at?: string | null;
  created_at: string;
}
