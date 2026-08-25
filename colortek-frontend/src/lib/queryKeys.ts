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
} as const;
