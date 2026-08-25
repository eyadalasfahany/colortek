"use client";

import PermissionGate from "@/components/auth/permission-gate";
import { Alert, AlertDescription, AlertTitle } from "@/components/tailgrids/core/alert";
import { Card, CardDescription, CardTitle } from "@/components/tailgrids/core/card";
import { Skeleton } from "@/components/tailgrids/core/skeleton";
import { queryKeys } from "@/lib/queryKeys";
import { getSiteDashboard } from "@/services/dashboardService";
import Link from "next/link";
import { useQuery } from "@tanstack/react-query";

export default function SiteDashboardView() {
  return (
    <PermissionGate permission="site.view">
      <SiteDashboardContent />
    </PermissionGate>
  );
}

function SiteDashboardContent() {
  const query = useQuery({
    queryKey: queryKeys.dashboard.site(),
    queryFn: getSiteDashboard,
  });

  return (
    <div className="px-4 pt-6 lg:px-6" dir="auto">
      <div className="mb-6">
        <h1 className="text-2xl font-semibold text-text-primary">Site</h1>
        <p className="mt-1 text-sm text-text-secondary">
          Active sites, readiness, crew logs today, and corrective actions.
        </p>
      </div>

      {query.isLoading ? <Skeleton className="h-48" /> : null}

      {query.isError ? (
        <Alert status="error">
          <AlertTitle>Could not load site dashboard</AlertTitle>
          <AlertDescription>
            {query.error instanceof Error ? query.error.message : "Something went wrong."}
          </AlertDescription>
        </Alert>
      ) : null}

      {query.isSuccess ? (
        <div className="grid gap-4 lg:grid-cols-2">
          <Card>
            <CardTitle>Not ready</CardTitle>
            {(query.data.not_ready ?? []).length === 0 ? (
              <CardDescription className="mt-2">All tracked sites are ready.</CardDescription>
            ) : (
              <ul className="mt-2 space-y-1 text-sm">
                {query.data.not_ready.map((site) => (
                  <li key={site.id}>
                    <Link href={`/projects/${site.reference}`} className="text-brand-500">
                      {site.reference} · {site.name}
                    </Link>
                  </li>
                ))}
              </ul>
            )}
          </Card>
          <Card>
            <CardTitle>Crew logs today</CardTitle>
            <CardDescription className="mt-2">
              {(query.data.crew_logs_today ?? []).length === 0
                ? "No submitted crew logs yet today."
                : `${query.data.crew_logs_today.length} log(s) submitted.`}
            </CardDescription>
            {(query.data.not_yet_reported ?? []).length > 0 ? (
              <p className="mt-2 text-sm text-warning-600">
                {(query.data.not_yet_reported as unknown[]).length} project(s) working today with
                no log yet.
              </p>
            ) : null}
          </Card>
          <Card className="lg:col-span-2">
            <CardTitle>Crew log</CardTitle>
            <CardDescription className="mt-2">
              <Link href="/crew-log" className="text-brand-500 hover:text-brand-600">
                Submit today&apos;s crew log →
              </Link>
            </CardDescription>
          </Card>
        </div>
      ) : null}
    </div>
  );
}
