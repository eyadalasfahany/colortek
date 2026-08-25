"use client";

import type { TaskDetail } from "@/types/api";
import { format } from "date-fns";

interface TimelineEntry {
  id: string;
  label: string;
  at: string;
  actor?: string;
}

export function buildTaskTimeline(task: TaskDetail): TimelineEntry[] {
  const entries: TimelineEntry[] = [];

  if (task.claimed_at) {
    entries.push({
      id: "claimed",
      label: "Claimed",
      at: task.claimed_at,
      actor: task.claimant?.name,
    });
  }

  if (task.started_at) {
    entries.push({
      id: "started",
      label: "Started",
      at: task.started_at,
      actor: task.claimant?.name,
    });
  }

  if (task.completed_at) {
    entries.push({
      id: "completed",
      label: "Completed",
      at: task.completed_at,
      actor: task.claimant?.name,
    });
  }

  entries.push({
    id: "status",
    label: `Status: ${task.status.replaceAll("_", " ")}`,
    at: task.completed_at ?? task.started_at ?? task.claimed_at ?? new Date().toISOString(),
  });

  return entries.sort(
    (a, b) => new Date(b.at).getTime() - new Date(a.at).getTime(),
  );
}

export function TaskActivitySection({
  task,
  comments,
}: {
  task: TaskDetail;
  comments: Array<{ id: string; body: string; created_at: string; author?: string }>;
}) {
  const timeline = buildTaskTimeline(task);

  return (
    <div className="mb-4 space-y-4">
      <section>
        <h3 className="mb-3 text-lg font-semibold text-text-primary">Activity</h3>
        <ul className="space-y-2 border-l-2 border-card-border pl-4">
          {timeline.map((entry) => (
            <li key={entry.id} className="text-sm">
              <p className="font-medium text-text-primary">{entry.label}</p>
              <p className="text-text-secondary">
                {format(new Date(entry.at), "d MMM yyyy, h:mm a")}
                {entry.actor ? ` · ${entry.actor}` : ""}
              </p>
            </li>
          ))}
        </ul>
      </section>

      {comments.length > 0 ? (
        <section>
          <h4 className="mb-2 text-sm font-semibold text-text-primary">Comments</h4>
          <ul className="space-y-2">
            {comments.map((comment) => (
              <li key={comment.id} className="rounded-lg border border-card-border p-3 text-sm">
                <p className="text-text-primary">{comment.body}</p>
                <p className="mt-1 text-xs text-text-tertiary">
                  {comment.author ?? "Someone"} ·{" "}
                  {format(new Date(comment.created_at), "d MMM h:mm a")}
                </p>
              </li>
            ))}
          </ul>
        </section>
      ) : null}
    </div>
  );
}
