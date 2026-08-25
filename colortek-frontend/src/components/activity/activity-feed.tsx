"use client";

import { Alert, AlertDescription, AlertTitle } from "@/components/tailgrids/core/alert";
import { Badge } from "@/components/tailgrids/core/badge";
import { Card, CardDescription, CardHeader, CardTitle } from "@/components/tailgrids/core/card";
import { Skeleton } from "@/components/tailgrids/core/skeleton";
import { queryKeys } from "@/lib/queryKeys";
import { getActivityFeed } from "@/services/activityService";
import type { ActivityEvent, ActivitySeverity } from "@/types/activity";
import { format } from "date-fns";
import Link from "next/link";
import { useMemo, useState } from "react";
import { useQuery } from "@tanstack/react-query";

function severityColor(severity: ActivitySeverity) {
  switch (severity) {
    case "blocker":
      return "error" as const;
    case "warning":
      return "warning" as const;
    case "success":
    case "approval":
      return "success" as const;
    default:
      return "gray" as const;
  }
}

function resolveActivityHref(event: ActivityEvent): string | null {
  if (event.link === "task" && event.link_params && typeof event.link_params.id === "number") {
    return `/tasks/${event.link_params.id}`;
  }
  if (event.link === "project" && event.project?.reference) {
    return `/projects/${event.project.reference}`;
  }
  if (event.project?.reference) {
    return `/projects/${event.project.reference}`;
  }
  return null;
}

export function ActivityFeed({
  title = "Activity",
  compact = false,
  projectId,
}: {
  title?: string;
  compact?: boolean;
  projectId?: number;
}) {
  const [severity, setSeverity] = useState<string>("");

  const query = useQuery({
    queryKey: [...queryKeys.activity.feed(), projectId, severity],
    queryFn: () =>
      getActivityFeed({
        per_page: compact ? 20 : 30,
        project_id: projectId,
        severity: severity || undefined,
      }),
  });

  const events = useMemo(() => query.data?.data ?? [], [query.data?.data]);

  return (
    <section className="space-y-3" dir="auto">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h2 className="text-lg font-semibold text-text-primary">{title}</h2>
        <select
          value={severity}
          onChange={(e) => setSeverity(e.target.value)}
          className="rounded-lg border border-card-border bg-card-bg px-2 py-1 text-sm"
          aria-label="Filter by severity"
        >
          <option value="">All severities</option>
          <option value="blocker">Blockers</option>
          <option value="warning">Warnings</option>
          <option value="approval">Approvals</option>
          <option value="success">Success</option>
          <option value="info">Info</option>
        </select>
      </div>

      {query.isLoading ? <Skeleton className="h-48" /> : null}

      {query.isError ? (
        <Alert status="error">
          <AlertTitle>Could not load activity</AlertTitle>
          <AlertDescription>
            {query.error instanceof Error ? query.error.message : "Something went wrong."}
          </AlertDescription>
        </Alert>
      ) : null}

      {query.isSuccess && events.length === 0 ? (
        <Card>
          <CardDescription>No activity yet.</CardDescription>
        </Card>
      ) : null}

      {query.isSuccess && events.length > 0 ? (
        <ul className="divide-y divide-card-border rounded-lg border border-card-border bg-card-bg">
          {events.map((event) => {
            const href = resolveActivityHref(event);
            const line = (
              <div className="flex flex-wrap items-baseline gap-x-2 gap-y-1 px-3 py-2 text-sm">
                <span className="shrink-0 text-xs text-text-tertiary">
                  {format(new Date(event.created_at), "HH:mm")}
                </span>
                <span className="text-text-secondary">
                  {event.department?.name ?? "System"}
                  {event.actor?.name ? ` · ${event.actor.name}` : ""}
                </span>
                {event.project?.reference ? (
                  <span className="font-medium text-text-primary">{event.project.reference}</span>
                ) : null}
                <span className="min-w-0 flex-1 text-text-primary">{event.message}</span>
                <Badge color={severityColor(event.severity)} size="sm">
                  {event.severity}
                </Badge>
              </div>
            );

            return (
              <li key={event.id}>
                {href ? (
                  <Link href={href} className="block hover:bg-background-gray-primary">
                    {line}
                  </Link>
                ) : (
                  line
                )}
              </li>
            );
          })}
        </ul>
      ) : null}
    </section>
  );
}

export function ActivityPageView() {
  return (
    <div className="px-4 pt-6 lg:px-6" dir="auto">
      <div className="mb-6">
        <h1 className="text-2xl font-semibold text-text-primary">Activity</h1>
        <p className="mt-1 text-sm text-text-secondary">
          Operations log — blockers and approvals stand out from routine events.
        </p>
      </div>
      <ActivityFeed />
    </div>
  );
}
