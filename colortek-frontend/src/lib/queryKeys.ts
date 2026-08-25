export const queryKeys = {
  auth: { me: () => ["auth", "me"] as const },
  tasks: {
    all: () => ["tasks"] as const,
    list: (scope: "queue" | "my" | "all") => ["tasks", "list", scope] as const,
    detail: (id: number) => ["tasks", "detail", id] as const,
  },
  samples: {
    all: () => ["samples"] as const,
    list: (params?: Record<string, unknown>) => ["samples", "list", params] as const,
    detail: (reference: string) => ["samples", "detail", reference] as const,
    chain: (reference: string) => ["samples", "chain", reference] as const,
  },
  formulas: { list: (sampleReference: string) => ["formulas", "list", sampleReference] as const },
  employees: { list: () => ["employees", "list"] as const },
  siteVisits: {
    all: () => ["siteVisits"] as const,
    detail: (id: number) => ["siteVisits", "detail", id] as const,
    checklist: () => ["siteVisits", "checklist"] as const,
  },
  dashboard: {
    controlRoom: () => ["dashboard", "control-room"] as const,
    workshop: () => ["dashboard", "workshop"] as const,
    site: () => ["dashboard", "site"] as const,
    samples: () => ["dashboard", "samples"] as const,
  },
  projects: {
    list: (params?: Record<string, unknown>) => ["projects", "list", params] as const,
    detail: (reference: string) => ["projects", "detail", reference] as const,
    workflow: (id: number) => ["projects", "workflow", id] as const,
    activity: (id: number) => ["projects", "activity", id] as const,
  },
  notifications: {
    list: () => ["notifications", "list"] as const,
    unread: () => ["notifications", "unread"] as const,
  },
  activity: { feed: () => ["activity", "feed"] as const },
} as const;
