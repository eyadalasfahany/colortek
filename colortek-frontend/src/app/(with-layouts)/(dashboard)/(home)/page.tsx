"use client";

import { ActiveProjects } from "@/components/control-room/active-projects";
import { KpiRow } from "@/components/control-room/kpi-row";
import { LiveFeed } from "@/components/control-room/live-feed";
import { NeedsAttention } from "@/components/control-room/needs-attention";
import { useAuth } from "@/context/auth-context";
import { useActivityStream } from "@/hooks/use-activity-stream";
import { getControlRoom } from "@/services/dashboardService";
import { queryKeys } from "@/lib/queryKeys";
import { useQuery } from "@tanstack/react-query";
import { useRouter } from "next/navigation";
import { useEffect } from "react";

export default function ControlRoomPage() {
  const { user, isLoading } = useAuth();
  const router = useRouter();
  const canView = user?.permissions.includes("project.view_all") ?? false;

  useEffect(() => {
    if (!isLoading && user && !canView) router.replace("/my-tasks");
  }, [canView, isLoading, router, user]);

  const dashboardQuery = useQuery({
    queryKey: queryKeys.dashboard.controlRoom(),
    queryFn: getControlRoom,
    enabled: canView,
    refetchInterval: 60000,
  });

  const { events, connected } = useActivityStream(canView);

  if (isLoading || !canView) {
    return (
      <div className="animate-pulse space-y-4 px-4 pt-6 lg:px-6">
        <div className="h-8 w-48 rounded bg-[var(--color-neutral-100)]" />
        <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
          {Array.from({ length: 4 }).map((_, i) => (
            <div key={i} className="h-20 rounded-lg bg-[var(--color-neutral-100)]" />
          ))}
        </div>
      </div>
    );
  }

  const data = dashboardQuery.data;

  return (
    <div className="space-y-5 px-4 pt-6 lg:px-6">
      <div>
        <h1 className="text-[28px] font-bold text-text-primary">Control Room</h1>
        <p className="text-sm text-text-secondary">What is happening and what is stuck — live.</p>
      </div>

      {data ? <KpiRow kpis={data.kpis} /> : null}

      <div className="grid grid-cols-1 gap-5 xl:grid-cols-[1fr_1fr_1fr]">
        <div className="order-2 xl:order-1">
          <NeedsAttention
            blockers={data?.needs_attention.blockers ?? []}
            sitesNotReady={data?.needs_attention.sites_not_ready ?? []}
          />
        </div>
        <div className="order-1 xl:order-2">
          <LiveFeed events={events} connected={connected} />
        </div>
        <div className="order-3 xl:order-3">
          {data ? <ActiveProjects projects={data.active_projects} /> : null}
        </div>
      </div>
    </div>
  );
}
