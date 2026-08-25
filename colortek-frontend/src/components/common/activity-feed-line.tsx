"use client";

import { cn } from "@/utils/cn";
import type { ActivityEvent } from "@/types/live";

const severityClass: Record<string, string> = {
  info: "border-l-[var(--color-blue)]",
  success: "border-l-[var(--color-green)]",
  warning: "border-l-[var(--color-orange)]",
  blocker: "border-l-[var(--color-status-danger,#C6392E)]",
};

interface ActivityFeedLineProps {
  event: ActivityEvent;
  className?: string;
}

export function ActivityFeedLine({ event, className }: ActivityFeedLineProps) {
  return (
    <div
      className={cn(
        "flex items-start gap-3 border-l-2 py-1.5 ps-3 text-sm leading-5",
        severityClass[event.severity] ?? severityClass.info,
        className,
      )}
    >
      <div className="min-w-0 flex-1">
        <p className="text-text-primary">{event.message}</p>
        <p className="mt-0.5 text-xs text-text-tertiary">
          {event.project?.reference ? `${event.project.reference} · ` : ""}
          {event.created_at ? new Date(event.created_at).toLocaleString() : ""}
        </p>
      </div>
    </div>
  );
}
