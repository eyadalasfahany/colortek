import { axiosInstance } from "@/config/axios";

export interface SearchResultGroup {
  projects: Array<{ type: string; id: number; label: string; reference?: string }>;
  tasks: Array<{ type: string; id: number; label: string; project_reference?: string }>;
  clients: Array<{ type: string; id: number; label: string }>;
  samples: unknown[];
  site_visits: unknown[];
}

export async function globalSearch(q: string): Promise<SearchResultGroup> {
  const res = await axiosInstance.get<{ data: SearchResultGroup }>("/search", { params: { q } });
  return res.data;
}
