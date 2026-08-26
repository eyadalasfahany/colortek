"use client";

import PermissionGate from "@/components/auth/permission-gate";
import { Alert, AlertDescription, AlertTitle } from "@/components/tailgrids/core/alert";
import { Card, CardDescription, CardTitle } from "@/components/tailgrids/core/card";
import { Skeleton } from "@/components/tailgrids/core/skeleton";
import { queryKeys } from "@/lib/queryKeys";
import { getWorkshopDashboard } from "@/services/dashboardService";
import { useQuery } from "@tanstack/react-query";
import { Link } from "@/i18n/navigation";

export default function WorkshopDashboardView() {
  return (
    <div className="px-4 pt-6 lg:px-6" dir="auto">
      <div className="mb-6">
        <h1 className="text-2xl font-semibold text-text-primary">Workshop</h1>
        <p className="mt-1 text-sm text-text-secondary">
          Samples in progress, blocked tasks, and live timers.
        </p>
      </div>
      <WorkshopContent />
    </div>
  );
}

function WorkshopContent() {
  const query = useQuery({
    queryKey: queryKeys.dashboard.workshop(),
    queryFn: getWorkshopDashboard,
  });

  if (query.isLoading) return <Skeleton className="h-48" />;

  if (query.isError) {
    return (
      <Alert status="error">
        <AlertTitle>Could not load workshop dashboard</AlertTitle>
        <AlertDescription>
          {query.error instanceof Error ? query.error.message : "Something went wrong."}
        </AlertDescription>
      </Alert>
    );
  }

  const blocked = (query.data?.blocked ?? []) as Array<{ id?: number; title?: string }>;

  return (
    <div className="grid gap-4 lg:grid-cols-2">
      <Card>
        <CardTitle>In progress</CardTitle>
        <CardDescription className="mt-2">
          {(query.data?.in_progress ?? []).length} task(s) running.
        </CardDescription>
      </Card>
      <Card>
        <CardTitle>Blocked</CardTitle>
        {blocked.length === 0 ? (
          <CardDescription className="mt-2">Nothing blocked.</CardDescription>
        ) : (
          <ul className="mt-2 space-y-1 text-sm">
            {blocked.map((task, i) => (
              <li key={task.id ?? i}>
                {task.id ? (
                  <Link href={`/tasks/${task.id}`} className="text-brand-500">
                    {task.title ?? `Task #${task.id}`}
                  </Link>
                ) : (
                  task.title
                )}
              </li>
            ))}
          </ul>
        )}
      </Card>
    </div>
  );
}
