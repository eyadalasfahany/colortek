"use client";

import { cn } from "@/utils/cn";

export function DeadlineLabel({ dueAt, isOverdue }: { dueAt: string | null; isOverdue?: boolean }) {
  if (!dueAt) return <span className="text-xs text-text-tertiary">No deadline</span>;

  const due = new Date(dueAt);
  const now = new Date();
  const diffMs = due.getTime() - now.getTime();
  const diffHours = Math.round(diffMs / (1000 * 60 * 60));
  const diffDays = Math.round(diffMs / (1000 * 60 * 60 * 24));

  let label = "";
  if (isOverdue || diffMs < 0) {
    const late = Math.abs(diffDays >= 1 ? diffDays : diffHours);
    label = diffDays <= -1 || diffDays >= 1 ? `${late} days late` : `${late} hours late`;
  } else if (diffDays >= 1) {
    label = `due in ${diffDays} days`;
  } else {
    label = `due in ${Math.max(diffHours, 1)} hours`;
  }

  return (
    <span
      className={cn(
        "text-xs font-medium",
        isOverdue || diffMs < 0 ? "text-[var(--color-orange)]" : "text-text-secondary",
      )}
    >
      {label}
    </span>
  );
}
