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
  admin: {
    settings: () => ["admin", "settings"] as const,
    holidays: () => ["admin", "holidays"] as const,
    roles: () => ["admin", "roles"] as const,
    users: () => ["admin", "users"] as const,
    employees: () => ["admin", "employees"] as const,
    workflows: () => ["admin", "workflows"] as const,
    checklist: () => ["admin", "checklist"] as const,
    failures: (tab: string) => ["admin", "failures", tab] as const,
    coverage: () => ["admin", "coverage"] as const,
    audit: (params?: Record<string, unknown>) => ["admin", "audit", params] as const,
  },
  peopleHours: {
    report: (params?: Record<string, unknown>) => ["peopleHours", "report", params] as const,
  },
  options: {
    projects: () => ["options", "projects"] as const,
    departments: () => ["options", "departments"] as const,
    employees: () => ["options", "employees"] as const,
    users: () => ["options", "users"] as const,
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
