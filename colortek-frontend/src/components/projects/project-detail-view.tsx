"use client";

import { ActivityFeed } from "@/components/activity/activity-feed";
import PermissionGate from "@/components/auth/permission-gate";
import { Alert, AlertDescription, AlertTitle } from "@/components/tailgrids/core/alert";
import { Badge } from "@/components/tailgrids/core/badge";
import { Card, CardDescription, CardTitle } from "@/components/tailgrids/core/card";
import { Skeleton } from "@/components/tailgrids/core/skeleton";
import { TabContent, TabList, TabRoot, TabTrigger } from "@/components/tailgrids/core/tabs";
import { queryKeys } from "@/lib/queryKeys";
import {
  getProjectByReference,
  getProjectPayments,
  getProjectTasks,
  getProjectWorkflow,
} from "@/services/projectService";
import type { ProjectDetail } from "@/types/projects";
import { formatDeadlineInWords } from "@/utils/task-formatters";
import { useQuery } from "@tanstack/react-query";
import { Link } from "@/i18n/navigation";

export default function ProjectDetailView({ reference }: { reference: string }) {
  return (
    <PermissionGate permission="project.view">
      <ProjectDetailContent reference={reference} />
    </PermissionGate>
  );
}

function ProjectDetailContent({ reference }: { reference: string }) {
  const projectQuery = useQuery({
    queryKey: queryKeys.projects.detail(reference),
    queryFn: () => getProjectByReference(reference),
  });

  const project = projectQuery.data;

  const workflowQuery = useQuery({
    queryKey: queryKeys.projects.workflow(project?.id ?? 0),
    queryFn: () => getProjectWorkflow(project!.id),
    enabled: Boolean(project?.id),
  });

  const tasksQuery = useQuery({
    queryKey: queryKeys.projects.list({ projectId: project?.id }),
    queryFn: () => getProjectTasks(project!.id, { per_page: 50 }),
    enabled: Boolean(project?.id),
  });

  const paymentsQuery = useQuery({
    queryKey: ["projects", project?.id, "payments"],
    queryFn: () => getProjectPayments(project!.id),
    enabled: Boolean(project?.id),
  });

  if (projectQuery.isLoading) {
    return <ProjectDetailSkeleton />;
  }

  if (projectQuery.isError || !project) {
    return (
      <div className="px-4 pt-6 lg:px-6">
        <Alert status="error">
          <AlertTitle>Project not found</AlertTitle>
          <AlertDescription>
            {projectQuery.error instanceof Error
              ? projectQuery.error.message
              : "This project is unavailable."}
          </AlertDescription>
        </Alert>
      </div>
    );
  }

  const openTasks = (tasksQuery.data?.data ?? []).filter((t) => t.status !== "completed");
  const nextAction = workflowQuery.data?.next_action;

  return (
    <div className="px-4 pt-6 lg:px-6" dir="auto">
      <div className="mb-2">
        <Link href="/projects" className="text-sm text-text-secondary hover:text-text-primary">
          ← Projects
        </Link>
      </div>

      <ProjectHeader project={project} />

      {workflowQuery.data ? (
        <Card className="mb-4 overflow-x-auto">
          <CardTitle className="mb-3 text-base">Workflow</CardTitle>
          <div className="flex min-w-max gap-2">
            {workflowQuery.data.stages.map((stage) => (
              <div
                key={stage.key}
                className="flex min-w-24 flex-col items-center rounded-lg border border-card-border px-2 py-2 text-center text-xs"
              >
                <span
                  className={
                    stage.state === "current"
                      ? "font-semibold text-brand-500"
                      : stage.state === "blocked"
                        ? "text-error-500"
                        : "text-text-secondary"
                  }
                >
                  {stage.label}
                </span>
                <span className="mt-1 text-text-tertiary">
                  {stage.state === "completed"
                    ? "✓"
                    : stage.state === "blocked"
                      ? "⛔"
                      : stage.state === "current"
                        ? "●"
                        : "·"}
                </span>
              </div>
            ))}
          </div>
          {nextAction ? (
            <p className="mt-4 text-base font-medium text-text-primary">
              Next: {nextAction}
            </p>
          ) : null}
        </Card>
      ) : workflowQuery.isLoading ? (
        <Skeleton className="mb-4 h-24" />
      ) : null}

      <TabRoot defaultValue="tasks">
        <TabList>
          <TabTrigger value="tasks">Tasks</TabTrigger>
          <TabTrigger value="payments">Payments</TabTrigger>
          <TabTrigger value="activity">Activity</TabTrigger>
        </TabList>

        <TabContent value="tasks" className="mt-4">
          {tasksQuery.isLoading ? <Skeleton className="h-32" /> : null}
          {openTasks.length === 0 ? (
            <Card>
              <CardDescription>No open tasks.</CardDescription>
            </Card>
          ) : (
            <ul className="space-y-2">
              {openTasks.map((task) => (
                <li key={task.id}>
                  <Link href={`/tasks/${task.id}`}>
                    <Card>
                      <div className="flex flex-wrap items-center justify-between gap-2">
                        <div>
                          <p className="font-medium text-text-primary">{task.title}</p>
                          <p className="text-sm text-text-secondary">
                            {task.department?.name ?? "—"} · {task.claimant?.name ?? "Unclaimed"}
                          </p>
                        </div>
                        <div className="flex flex-col items-end gap-1">
                          <Badge color={task.status === "blocked" ? "error" : "primary"} size="sm">
                            {task.status.replaceAll("_", " ")}
                          </Badge>
                          <span className="text-xs text-text-tertiary">
                            {formatDeadlineInWords(task.due_at, task.is_overdue)}
                          </span>
                        </div>
                      </div>
                    </Card>
                  </Link>
                </li>
              ))}
            </ul>
          )}
        </TabContent>

        <TabContent value="payments" className="mt-4">
          {paymentsQuery.isLoading ? <Skeleton className="h-24" /> : null}
          <Card>
            <CardDescription>
              {Array.isArray(paymentsQuery.data) && paymentsQuery.data.length > 0
                ? `${paymentsQuery.data.length} installment(s) on record.`
                : "No payments recorded yet."}
            </CardDescription>
          </Card>
        </TabContent>

        <TabContent value="activity" className="mt-4">
          {project.id ? <ActivityFeed projectId={project.id} compact /> : null}
        </TabContent>
      </TabRoot>
    </div>
  );
}

function ProjectHeader({ project }: { project: ProjectDetail }) {
  return (
    <Card className="mb-4">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <p className="text-2xl font-semibold text-text-primary">
            {project.reference} · {project.name}
          </p>
          <p className="mt-1 text-sm text-text-secondary">
            Client: {project.client?.name ?? project.client_name ?? "—"} · Sales:{" "}
            {project.sales_user?.name ?? "—"}
          </p>
          <p className="mt-1 text-sm capitalize text-text-secondary">Stage: {project.stage}</p>
        </div>
        <div className="flex flex-wrap gap-2">
          {!project.site_ready ? (
            <Badge color="error" size="md">
              Site not ready
            </Badge>
          ) : null}
          <Badge color="gray" size="md">
            {project.status}
          </Badge>
        </div>
      </div>
    </Card>
  );
}

function ProjectDetailSkeleton() {
  return (
    <div className="space-y-4 px-4 pt-6 lg:px-6">
      <Skeleton className="h-4 w-24" />
      <Skeleton className="h-28" />
      <Skeleton className="h-48" />
    </div>
  );
}
