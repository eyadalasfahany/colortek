"use client";

import PermissionGate from "@/components/auth/permission-gate";
import { ActivityFeed } from "@/components/activity/activity-feed";
import { Alert, AlertDescription, AlertTitle } from "@/components/tailgrids/core/alert";
import { Badge } from "@/components/tailgrids/core/badge";
import { Card, CardDescription, CardHeader, CardTitle } from "@/components/tailgrids/core/card";
import { Skeleton } from "@/components/tailgrids/core/skeleton";
import { queryKeys } from "@/lib/queryKeys";
import { getControlRoomDashboard } from "@/services/dashboardService";
import { useQuery } from "@tanstack/react-query";
import Link from "next/link";

export default function ControlRoomView() {
  return (
    <PermissionGate permission="project.view_all">
      <ControlRoomContent />
    </PermissionGate>
  );
}

function ControlRoomContent() {
  const query = useQuery({
    queryKey: queryKeys.dashboard.controlRoom(),
    queryFn: getControlRoomDashboard,
  });

  return (
    <div className="space-y-6 px-4 pt-6 lg:px-6" dir="auto">
      <div>
        <h1 className="text-2xl font-semibold text-text-primary">Control Room</h1>
        <p className="mt-1 text-sm text-text-secondary">
          What is happening right now, and what is stuck.
        </p>
      </div>

      {query.isLoading ? <ControlRoomSkeleton /> : null}

      {query.isError ? (
        <Alert status="error">
          <AlertTitle>Could not load dashboard</AlertTitle>
          <AlertDescription>
            {query.error instanceof Error ? query.error.message : "Something went wrong."}
          </AlertDescription>
        </Alert>
      ) : null}

      {query.isSuccess ? (
        <>
          <div className="grid grid-cols-2 gap-3 lg:grid-cols-4 xl:grid-cols-7">
            {query.data.kpis.map((kpi) => (
              <Link key={kpi.key} href={kpi.filter_href}>
                <Card className={kpi.count === 0 ? "opacity-70" : ""}>
                  <CardDescription className="text-xs uppercase">{kpi.label}</CardDescription>
                  <CardTitle className="mt-1 text-2xl">{kpi.count}</CardTitle>
                </Card>
              </Link>
            ))}
          </div>

          <div className="grid gap-6 xl:grid-cols-[1fr_1fr_320px]">
            <ActivityFeed title="Live feed" compact />

            <section className="space-y-3">
              <h2 className="text-lg font-semibold text-text-primary">Active projects</h2>
              {query.data.active_projects.length === 0 ? (
                <Card>
                  <CardDescription>No active projects yet.</CardDescription>
                </Card>
              ) : (
                query.data.active_projects.map((project) => (
                  <Link key={project.id} href={`/projects/${project.reference}`}>
                    <Card>
                      <CardHeader className="items-start gap-2">
                        <div>
                          <CardTitle className="text-base">
                            {project.reference} · {project.name}
                          </CardTitle>
                          <CardDescription className="mt-1">
                            {project.client_name ?? "—"} · Sales: {project.sales_user ?? "—"}
                          </CardDescription>
                          <p className="mt-2 text-sm text-text-primary">
                            <span className="font-medium">Next:</span>{" "}
                            {project.next_action ?? "—"}
                          </p>
                        </div>
                        {!project.site_ready ? (
                          <Badge color="error" size="sm">
                            Site not ready
                          </Badge>
                        ) : null}
                      </CardHeader>
                    </Card>
                  </Link>
                ))
              )}
            </section>

            <section className="space-y-4">
              <h2 className="text-lg font-semibold text-text-primary">Needs attention</h2>
              <AttentionList
                title="Blockers"
                empty="No blocked tasks."
                items={query.data.needs_attention.blockers.map((b) => ({
                  key: String(b.task_id),
                  label: b.title,
                  href: `/tasks/${b.task_id}`,
                  meta: b.project_reference ?? undefined,
                }))}
              />
              <AttentionList
                title="Sites not ready"
                empty="All sites ready."
                items={query.data.needs_attention.sites_not_ready.map((s) => ({
                  key: String(s.project_id),
                  label: `${s.reference} · ${s.name}`,
                  href: `/projects/${s.reference}`,
                }))}
              />
            </section>
          </div>
        </>
      ) : null}
    </div>
  );
}

function AttentionList({
  title,
  empty,
  items,
}: {
  title: string;
  empty: string;
  items: Array<{ key: string; label: string; href: string; meta?: string }>;
}) {
  return (
    <Card>
      <CardTitle className="text-base">{title}</CardTitle>
      {items.length === 0 ? (
        <CardDescription className="mt-2">{empty}</CardDescription>
      ) : (
        <ul className="mt-3 space-y-2">
          {items.map((item) => (
            <li key={item.key}>
              <Link href={item.href} className="block text-sm text-brand-500 hover:text-brand-600">
                {item.label}
                {item.meta ? (
                  <span className="mt-0.5 block text-xs text-text-tertiary">{item.meta}</span>
                ) : null}
              </Link>
            </li>
          ))}
        </ul>
      )}
    </Card>
  );
}

function ControlRoomSkeleton() {
  return (
    <div className="space-y-4">
      <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
        {Array.from({ length: 4 }).map((_, i) => (
          <Skeleton key={i} className="h-20" />
        ))}
      </div>
      <Skeleton className="h-64" />
    </div>
  );
}
