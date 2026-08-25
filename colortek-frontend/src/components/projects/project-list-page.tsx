"use client";

import PermissionGate from "@/components/auth/permission-gate";
import { Alert, AlertDescription, AlertTitle } from "@/components/tailgrids/core/alert";
import { Badge } from "@/components/tailgrids/core/badge";
import { Card, CardDescription, CardHeader, CardTitle } from "@/components/tailgrids/core/card";
import { Input } from "@/components/tailgrids/core/input";
import { Skeleton } from "@/components/tailgrids/core/skeleton";
import { queryKeys } from "@/lib/queryKeys";
import { getProjects } from "@/services/projectService";
import type { ProjectSummary } from "@/types/projects";
import Link from "next/link";
import { useMemo, useState } from "react";
import { useQuery } from "@tanstack/react-query";

export default function ProjectListPage() {
  return (
    <PermissionGate permission="project.view">
      <ProjectListContent />
    </PermissionGate>
  );
}

function ProjectListContent() {
  const [search, setSearch] = useState("");
  const [stage, setStage] = useState("");

  const query = useQuery({
    queryKey: queryKeys.projects.list({ q: search, stage }),
    queryFn: () => getProjects({ q: search || undefined, stage: stage || undefined, per_page: 50 }),
  });

  const projects = query.data?.data ?? [];

  return (
    <div className="px-4 pt-6 lg:px-6" dir="auto">
      <div className="mb-6">
        <h1 className="text-2xl font-semibold text-text-primary">Projects</h1>
        <p className="mt-1 text-sm text-text-secondary">
          Search by reference, name or client.
        </p>
      </div>

      <div className="mb-4 flex flex-col gap-3 sm:flex-row">
        <Input
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          placeholder="Search projects…"
          className="w-full px-3 py-2.5 text-sm sm:max-w-xs"
        />
        <select
          value={stage}
          onChange={(e) => setStage(e.target.value)}
          className="rounded-lg border border-card-border bg-card-bg px-3 py-2.5 text-sm"
        >
          <option value="">All stages</option>
          <option value="lead">Lead</option>
          <option value="quotation">Quotation</option>
          <option value="payment">Payment</option>
          <option value="sample">Sample</option>
          <option value="site">Site</option>
          <option value="production">Production</option>
        </select>
      </div>

      {query.isLoading ? <ProjectListSkeleton /> : null}

      {query.isError ? (
        <Alert status="error">
          <AlertTitle>Could not load projects</AlertTitle>
          <AlertDescription>
            {query.error instanceof Error ? query.error.message : "Something went wrong."}
          </AlertDescription>
        </Alert>
      ) : null}

      {query.isSuccess ? (
        projects.length === 0 ? (
          <Card>
            <CardDescription>No projects match your filters.</CardDescription>
          </Card>
        ) : (
          <>
            <div className="hidden md:block">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-card-border text-left text-text-tertiary">
                    <th className="py-2 pr-4">Reference</th>
                    <th className="py-2 pr-4">Name</th>
                    <th className="py-2 pr-4">Client</th>
                    <th className="py-2 pr-4">Stage</th>
                    <th className="py-2">Status</th>
                  </tr>
                </thead>
                <tbody>
                  {projects.map((project) => (
                    <ProjectTableRow key={project.id} project={project} />
                  ))}
                </tbody>
              </table>
            </div>
            <div className="flex flex-col gap-3 md:hidden">
              {projects.map((project) => (
                <ProjectCard key={project.id} project={project} />
              ))}
            </div>
          </>
        )
      ) : null}
    </div>
  );
}

function ProjectTableRow({ project }: { project: ProjectSummary }) {
  return (
    <tr className="border-b border-card-border hover:bg-background-gray-primary">
      <td className="py-3 pr-4">
        <Link href={`/projects/${project.reference}`} className="font-medium text-brand-500">
          {project.reference}
        </Link>
      </td>
      <td className="py-3 pr-4">{project.name}</td>
      <td className="py-3 pr-4">{project.client_name ?? "—"}</td>
      <td className="py-3 pr-4 capitalize">{project.stage}</td>
      <td className="py-3">
        <ProjectStatusBadges project={project} />
      </td>
    </tr>
  );
}

function ProjectCard({ project }: { project: ProjectSummary }) {
  return (
    <Link href={`/projects/${project.reference}`}>
      <Card>
        <CardHeader>
          <CardTitle className="text-base">
            {project.reference} · {project.name}
          </CardTitle>
          <CardDescription>{project.client_name ?? "—"}</CardDescription>
          <div className="mt-2 flex flex-wrap gap-2">
            <Badge color="primary" size="sm">
              {project.stage}
            </Badge>
            <ProjectStatusBadges project={project} />
          </div>
        </CardHeader>
      </Card>
    </Link>
  );
}

function ProjectStatusBadges({ project }: { project: ProjectSummary }) {
  return (
    <>
      {!project.site_ready ? (
        <Badge color="error" size="sm">
          Site not ready
        </Badge>
      ) : null}
      <Badge color="gray" size="sm">
        {project.status}
      </Badge>
    </>
  );
}

function ProjectListSkeleton() {
  return (
    <div className="space-y-3">
      {Array.from({ length: 5 }).map((_, i) => (
        <Skeleton key={i} className="h-16" />
      ))}
    </div>
  );
}
