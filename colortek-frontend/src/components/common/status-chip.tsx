"use client";

import { cn } from "@/utils/cn";

type ChipVariant = "ready" | "in_progress" | "paused" | "waiting" | "blocked" | "completed" | "cancelled" | "overdue" | "site_not_ready";

const styles: Record<ChipVariant, string> = {
  ready: "bg-[var(--color-indigo)]/10 text-[var(--color-indigo)]",
  in_progress: "bg-[var(--color-blue)]/10 text-[var(--color-blue)]",
  paused: "bg-[var(--color-blue-bright)]/10 text-[var(--color-blue-bright)]",
  waiting: "bg-[var(--color-purple)]/10 text-[var(--color-purple)]",
  blocked: "bg-[var(--color-status-danger,#C6392E)]/10 text-[var(--color-status-danger,#C6392E)]",
  completed: "bg-[var(--color-green)]/10 text-[var(--color-green)]",
  cancelled: "line-through bg-[var(--color-neutral-400)]/10 text-[var(--color-neutral-500)]",
  overdue: "bg-[var(--color-orange)]/10 text-[var(--color-orange)]",
  site_not_ready: "bg-[var(--color-status-danger,#C6392E)]/10 text-[var(--color-status-danger,#C6392E)]",
};

export function StatusChip({ variant, label }: { variant: ChipVariant; label: string }) {
  return (
    <span className={cn("inline-flex rounded px-2 py-0.5 text-xs font-medium", styles[variant])}>
      {label}
    </span>
  );
}
