"use client";

import { ProjectCard } from "@/components/projects/project-card";
import type { ProjectCardData } from "@/types/live";

export function ActiveProjects({ projects }: { projects: ProjectCardData[] }) {
  return (
    <section>
      <h2 className="mb-3 text-sm font-semibold text-text-primary">Active projects</h2>
      <div className="grid gap-3 md:grid-cols-2">
        {projects.map((p) => (
          <ProjectCard key={p.id} project={p} />
        ))}
      </div>
    </section>
  );
}
