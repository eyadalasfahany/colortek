"use client";

import { cn } from "@/utils/cn";
import type { WorkflowStage } from "@/types/live";

export function WorkflowStrip({ stages }: { stages: WorkflowStage[] }) {
  return (
    <div className="flex flex-wrap gap-2">
      {stages.map((stage) => (
        <div
          key={stage.key}
          className={cn(
            "rounded px-3 py-1.5 text-xs font-medium",
            !stage.configured && "opacity-50",
            stage.active && "bg-[var(--color-orange)] text-white",
            stage.completed && !stage.active && "bg-[var(--color-green)]/15 text-[var(--color-green)]",
            !stage.active && !stage.completed && "bg-[var(--color-neutral-100)] text-text-secondary",
            stage.blocked && "ring-1 ring-[var(--color-status-danger,#C6392E)]",
          )}
          title={!stage.configured ? "Not configured yet" : stage.label}
        >
          {stage.label}
        </div>
      ))}
    </div>
  );
}
