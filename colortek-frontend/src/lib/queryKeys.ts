export const queryKeys = {
  auth: {
    me: () => ["auth", "me"] as const,
  },
  tasks: {
    all: () => ["tasks"] as const,
    list: (scope: "queue" | "my" | "all") => ["tasks", "list", scope] as const,
    detail: (id: number) => ["tasks", "detail", id] as const,
  },
} as const;
