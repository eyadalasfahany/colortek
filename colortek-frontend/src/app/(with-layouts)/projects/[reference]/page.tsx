"use client";

import { ActivityFeedLine } from "@/components/common/activity-feed-line";
import { StatusChip } from "@/components/common/status-chip";
import { WorkflowStrip } from "@/components/common/workflow-strip";
import {
  fetchProjectActivity,
  fetchProjectByReference,
  fetchProjectWorkflow,
} from "@/services/projectService";
import { queryKeys } from "@/lib/queryKeys";
import { useQuery } from "@tanstack/react-query";
import Link from "next/link";
import { useParams } from "next/navigation";
import { useState } from "react";

const TABS = ["Tasks", "People", "Samples", "Site", "Payments", "Activity", "Files"] as const;

export default function ProjectDetailPage() {
  const params = useParams<{ reference: string }>();
  const reference = params.reference;
  const [tab, setTab] = useState<(typeof TABS)[number]>("Tasks");

  const projectQuery = useQuery({
    queryKey: queryKeys.projects.detail(reference),
    queryFn: () => fetchProjectByReference(reference),
  });

  const workflowQuery = useQuery({
    queryKey: queryKeys.projects.workflow(projectQuery.data?.id ?? 0),
    queryFn: () => fetchProjectWorkflow(projectQuery.data!.id),
    enabled: Boolean(projectQuery.data?.id),
  });

  const activityQuery = useQuery({
    queryKey: queryKeys.projects.activity(projectQuery.data?.id ?? 0),
    queryFn: () => fetchProjectActivity(projectQuery.data!.id),
    enabled: Boolean(projectQuery.data?.id) && tab === "Activity",
  });

  const project = projectQuery.data;
  const workflow = workflowQuery.data;

  if (projectQuery.isLoading) {
    return <div className="mx-4 mt-6 h-40 animate-pulse rounded-lg bg-[var(--color-neutral-100)] lg:mx-6" />;
  }

  if (!project) {
    return <p className="px-6 pt-6 text-sm text-text-tertiary">Project not found.</p>;
  }

  return (
    <div className="px-4 pt-6 lg:px-6">
      <div className="mb-4">
        <p className="text-sm text-text-tertiary">{project.client?.name}</p>
        <h1 className="text-2xl font-semibold text-text-primary">
          {project.reference} — {project.name}
        </h1>
        <div className="mt-2 flex flex-wrap gap-2">
          <StatusChip variant="ready" label={project.stage} />
          {project.status === "cancelled" ? <StatusChip variant="cancelled" label="Cancelled" /> : null}
          {project.status === "completed" ? <StatusChip variant="completed" label="Completed" /> : null}
          {project.site_ready === false ? <StatusChip variant="site_not_ready" label="Site not ready" /> : null}
        </div>
      </div>

      {workflow ? (
        <div className="mb-4 space-y-3">
          <WorkflowStrip stages={workflow.stages} />
          {workflow.next_action ? (
            <p className="text-sm text-text-primary">
              <span className="font-semibold text-[var(--color-orange)]">Next:</span>{" "}
              <Link href={`/tasks/${workflow.next_action.task_id}`} className="underline">
                {workflow.next_action.title}
              </Link>
              {" · "}
              {workflow.next_action.holder}
            </p>
          ) : null}
        </div>
      ) : null}

      <div className="mb-4 flex flex-wrap gap-2 border-b border-card-border">
        {TABS.map((t) => (
          <button
            key={t}
            type="button"
            onClick={() => setTab(t)}
            className={
              tab === t
                ? "border-b-2 border-[var(--color-orange)] px-3 py-2 text-sm font-medium text-text-primary"
                : "px-3 py-2 text-sm text-text-secondary"
            }
          >
            {t}
          </button>
        ))}
      </div>

      {tab === "Tasks" ? (
        <p className="text-sm text-text-secondary">
          <Link href={`/queue?project=${project.id}`} className="text-[var(--color-orange)] underline">
            View project tasks in queue
          </Link>
        </p>
      ) : null}
      {tab === "Activity" ? (
        <div className="space-y-1">
          {activityQuery.data?.map((e) => (
            <ActivityFeedLine key={e.id} event={e} />
          ))}
        </div>
      ) : null}
      {tab !== "Tasks" && tab !== "Activity" ? (
        <p className="text-sm text-text-tertiary">Section coming soon — stub data from API where available.</p>
      ) : null}
    </div>
  );
}
