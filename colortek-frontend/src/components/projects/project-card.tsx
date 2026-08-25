"use client";

import { StatusChip } from "@/components/common/status-chip";
import type { ProjectCardData } from "@/types/live";
import Link from "next/link";

export function ProjectCard({ project }: { project: ProjectCardData }) {
  return (
    <Link
      href={`/projects/${project.reference}`}
      className="block rounded-lg border border-card-border bg-card-background p-4 transition hover:border-[var(--color-orange)]/40"
    >
      <div className="flex items-start justify-between gap-2">
        <div>
          <p className="font-semibold text-text-primary">{project.reference}</p>
          <p className="text-sm text-text-secondary">{project.name}</p>
        </div>
        {project.site_ready === false ? (
          <StatusChip variant="site_not_ready" label="Site not ready" />
        ) : null}
      </div>
      {project.next_action ? (
        <p className="mt-3 text-sm text-text-primary">
          <span className="font-medium text-[var(--color-orange)]">Next:</span>{" "}
          {project.next_action.title}
        </p>
      ) : null}
    </Link>
  );
}
