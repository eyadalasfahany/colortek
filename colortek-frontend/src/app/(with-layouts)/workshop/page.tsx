"use client";

import { getWorkshopDashboard } from "@/services/dashboardService";
import { queryKeys } from "@/lib/queryKeys";
import { useQuery } from "@tanstack/react-query";

export default function WorkshopDashboardPage() {
  const q = useQuery({
    queryKey: queryKeys.dashboard.workshop(),
    queryFn: getWorkshopDashboard,
  });

  return (
    <div className="px-4 pt-6 lg:px-6">
      <h1 className="text-2xl font-semibold text-text-primary">Workshop</h1>
      <p className="mt-1 text-sm text-text-secondary">What needs doing now.</p>
      {q.isLoading ? (
        <div className="mt-6 h-40 animate-pulse rounded-lg bg-[var(--color-neutral-100)]" />
      ) : (
        <pre className="mt-6 overflow-auto rounded-lg border border-card-border bg-card-background p-4 text-xs text-text-secondary">
          {JSON.stringify(q.data, null, 2)}
        </pre>
      )}
    </div>
  );
}
