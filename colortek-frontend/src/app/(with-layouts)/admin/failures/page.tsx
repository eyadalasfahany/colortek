"use client";

import AdminPageHeader from "@/components/admin/admin-page-header";
import { Button } from "@/components/tailgrids/core/button";
import { TableBody, TableCell, TableHead, TableHeader, TableRoot, TableRow } from "@/components/tailgrids/core/table";
import { TabRoot, TabContent, TabList, TabTrigger } from "@/components/tailgrids/core/tabs";
import { queryKeys } from "@/lib/queryKeys";
import {
  getFailedJobs,
  getStalledInstances,
  getUnclaimedTasks,
  retryFailedJob,
} from "@/services/admin/adminService";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

export default function AdminFailuresPage() {
  const queryClient = useQueryClient();

  const stalledQuery = useQuery({
    queryKey: queryKeys.admin.failures("stalled"),
    queryFn: getStalledInstances,
  });
  const unclaimedQuery = useQuery({
    queryKey: queryKeys.admin.failures("unclaimed"),
    queryFn: getUnclaimedTasks,
  });
  const failedQuery = useQuery({
    queryKey: queryKeys.admin.failures("jobs"),
    queryFn: getFailedJobs,
  });

  const retryMutation = useMutation({
    mutationFn: retryFailedJob,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: queryKeys.admin.failures("jobs") }),
  });

  const stalled = stalledQuery.data?.data ?? [];
  const unclaimed = unclaimedQuery.data?.data ?? [];
  const failed = failedQuery.data?.data ?? [];
  const coverage = (stalledQuery.data?.meta?.coverage_warnings as Array<{ permission: string; description: string }>) ?? [];

  return (
    <div className="space-y-6 pb-8">
      <AdminPageHeader
        title="Operational failures"
        description="Diagnostic lists — not email alerts. Use these to find stalled workflows, overdue queues, and failed jobs."
        trail={[
          { href: "/", label: "Home" },
          { href: "/admin/failures", label: "Failures" },
        ]}
      />

      <div className="px-2 lg:px-6">
        <TabRoot defaultValue="stalled">
          <TabList>
            <TabTrigger value="stalled">Stalled instances</TabTrigger>
            <TabTrigger value="unclaimed">Unclaimed queues</TabTrigger>
            <TabTrigger value="jobs">Failed jobs</TabTrigger>
          </TabList>

          <TabContent value="stalled">
            {coverage.length > 0 ? (
              <p className="mb-3 text-sm text-status-warning">
                Coverage: {coverage.map((c) => c.description).join(" · ")}
              </p>
            ) : null}
            <TableRoot>
              <TableHeader>
                <TableRow>
                  <TableHead>Template</TableHead>
                  <TableHead>Project</TableHead>
                  <TableHead>Stalled since</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {stalled.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={3} className="py-8 text-center text-text-secondary">
                      No stalled instances — every running workflow has at least one open task.
                    </TableCell>
                  </TableRow>
                ) : (
                  stalled.map((row) => (
                    <TableRow key={row.id}>
                      <TableCell>{row.template_code} v{row.template_version}</TableCell>
                      <TableCell>{row.project?.reference ?? "—"}</TableCell>
                      <TableCell>{row.stalled_since ?? "—"}</TableCell>
                    </TableRow>
                  ))
                )}
              </TableBody>
            </TableRoot>
          </TabContent>

          <TabContent value="unclaimed">
            <TableRoot>
              <TableHeader>
                <TableRow>
                  <TableHead>Task</TableHead>
                  <TableHead>Department</TableHead>
                  <TableHead>Past due (min)</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {unclaimed.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={3} className="py-8 text-center text-text-secondary">
                      No unclaimed overdue tasks in department queues.
                    </TableCell>
                  </TableRow>
                ) : (
                  unclaimed.map((row) => (
                    <TableRow key={row.id}>
                      <TableCell>{row.reference} — {row.title}</TableCell>
                      <TableCell>{row.department?.code ?? "—"}</TableCell>
                      <TableCell>{row.minutes_past_due}</TableCell>
                    </TableRow>
                  ))
                )}
              </TableBody>
            </TableRoot>
          </TabContent>

          <TabContent value="jobs">
            <TableRoot>
              <TableHeader>
                <TableRow>
                  <TableHead>Queue</TableHead>
                  <TableHead>Failed at</TableHead>
                  <TableHead>Exception</TableHead>
                  <TableHead />
                </TableRow>
              </TableHeader>
              <TableBody>
                {failed.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={4} className="py-8 text-center text-text-secondary">
                      No failed queue jobs.
                    </TableCell>
                  </TableRow>
                ) : (
                  failed.map((row) => (
                    <TableRow key={row.uuid}>
                      <TableCell>{row.queue}</TableCell>
                      <TableCell>{row.failed_at}</TableCell>
                      <TableCell className="max-w-md truncate text-xs">{row.exception.slice(0, 120)}</TableCell>
                      <TableCell>
                        <Button size="sm" onClick={() => retryMutation.mutate(row.uuid)}>
                          Retry
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))
                )}
              </TableBody>
            </TableRoot>
          </TabContent>
        </TabRoot>
      </div>
    </div>
  );
}
