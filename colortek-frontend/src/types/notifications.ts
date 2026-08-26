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

export interface SearchResultItem {
  type: "project" | "task" | "client" | "sample" | "site_visit" | "formula";
  id: number;
  label: string;
  reference?: string;
  project_reference?: string;
}

export interface SearchResults {
  projects: SearchResultItem[];
  tasks: SearchResultItem[];
  clients: SearchResultItem[];
  samples: SearchResultItem[];
  site_visits: SearchResultItem[];
  formulas?: SearchResultItem[];
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null;
}

export function isAppNotification(value: unknown): value is AppNotification {
  return isRecord(value) && typeof value.id === "string" && typeof value.message === "string";
}

export function isSearchResults(value: unknown): value is SearchResults {
  return isRecord(value) && Array.isArray(value.projects);
}
