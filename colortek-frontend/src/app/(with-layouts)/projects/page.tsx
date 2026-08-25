"use client";

import { ProjectCard } from "@/components/projects/project-card";
import { fetchProjects } from "@/services/projectService";
import { queryKeys } from "@/lib/queryKeys";
import { useQuery } from "@tanstack/react-query";
import { useState } from "react";

export default function ProjectsPage() {
  const [blocked, setBlocked] = useState(false);
  const params = blocked ? { blocked: "1" } : undefined;

  const projectsQuery = useQuery({
    queryKey: queryKeys.projects.list(params),
    queryFn: () => fetchProjects(params),
  });

  return (
    <div className="px-4 pt-6 lg:px-6">
      <div className="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold text-text-primary">Projects</h1>
          <p className="text-sm text-text-secondary">All jobs you can see.</p>
        </div>
        <label className="flex items-center gap-2 text-sm text-text-secondary">
          <input type="checkbox" checked={blocked} onChange={(e) => setBlocked(e.target.checked)} />
          Blocked only
        </label>
      </div>

      {projectsQuery.isLoading ? (
        <div className="grid gap-3 md:grid-cols-2">
          {Array.from({ length: 4 }).map((_, i) => (
            <div key={i} className="h-28 animate-pulse rounded-lg bg-[var(--color-neutral-100)]" />
          ))}
        </div>
      ) : null}

      {projectsQuery.data?.length === 0 ? (
        <p className="text-sm text-text-tertiary">No projects match your filters.</p>
      ) : null}

      <div className="grid gap-3 md:grid-cols-2">
        {projectsQuery.data?.map((p) => (
          <ProjectCard
            key={p.id}
            project={{
              id: p.id,
              reference: p.reference,
              name: p.name,
              site_ready: p.site_ready,
            }}
          />
        ))}
      </div>
    </div>
  );
}
