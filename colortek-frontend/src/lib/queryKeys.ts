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
  },
} as const;
