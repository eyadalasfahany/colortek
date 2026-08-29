"use client";

import PermissionGate from "@/components/auth/permission-gate";
import CreateProjectDialog from "@/components/projects/create-project-dialog";
import { Button } from "@/components/tailgrids/core/button";
import {
  Alert,
  AlertDescription,
  AlertTitle,
} from "@/components/tailgrids/core/alert";
import { Badge } from "@/components/tailgrids/core/badge";
import {
  Card,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/tailgrids/core/card";
import { Input } from "@/components/tailgrids/core/input";
import { Skeleton } from "@/components/tailgrids/core/skeleton";
import { queryKeys } from "@/lib/queryKeys";
import { getEnumOptions } from "@/services/enumService";
import { getProjects } from "@/services/projectService";
import { usePermissions } from "@/hooks/use-permissions";
import type { ProjectSummary } from "@/types/projects";
import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { Link } from "@/i18n/navigation";
import { useTranslations } from "next-intl";
import { formatEnumLabel } from "@/utils/enum-label";

export default function ProjectListPage() {
  return (
    <PermissionGate permission="project.view">
      <ProjectListContent />
    </PermissionGate>
  );
}

function ProjectListContent() {
  const t = useTranslations("projects");
  const tStates = useTranslations("states");
  const tCommon = useTranslations("common");
  const { can } = usePermissions();
  const [search, setSearch] = useState("");
  const [stage, setStage] = useState("");
  const [createOpen, setCreateOpen] = useState(false);

  // Stages come from GET /enums/project_stage so the filter can never drift
  // out of sync with the backend enum.
  const stagesQuery = useQuery({
    queryKey: queryKeys.enums.options("project_stage"),
    queryFn: () => getEnumOptions("project_stage"),
  });

  const query = useQuery({
    queryKey: queryKeys.projects.list({ q: search, stage }),
    queryFn: () =>
      getProjects({
        q: search || undefined,
        stage: stage || undefined,
        per_page: 50,
      }),
  });

  const projects = query.data?.data ?? [];

  return (
    <div className="px-4 pt-6 lg:px-6">
      <div className="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold text-text-primary">
            {t("title")}
          </h1>
          <p className="mt-1 text-sm text-text-secondary">{t("description")}</p>
        </div>
        {can("project.create") ? (
          <Button
            variant="primary"
            appearance="fill"
            onPress={() => setCreateOpen(true)}
          >
            {t("createTitle")}
          </Button>
        ) : null}
      </div>

      <CreateProjectDialog isOpen={createOpen} onOpenChange={setCreateOpen} />

      <div className="mb-4 flex flex-col gap-3 sm:flex-row">
        <Input
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          placeholder={t("searchPlaceholder")}
          className="w-full px-3 py-2.5 text-sm sm:max-w-xs"
        />
        <select
          value={stage}
          onChange={(e) => setStage(e.target.value)}
          className="rounded-lg border border-card-border bg-card-bg px-3 py-2.5 text-sm"
        >
          <option value="">{t("stage")}</option>
          {(stagesQuery.data ?? []).map((option) => (
            <option key={option.value} value={option.value}>
              {option.label}
            </option>
          ))}
        </select>
      </div>

      {query.isLoading ? <ProjectListSkeleton /> : null}

      {query.isError ? (
        <Alert status="error">
          <AlertTitle>{tStates("error")}</AlertTitle>
          <AlertDescription>
            {query.error instanceof Error
              ? query.error.message
              : tStates("error")}
          </AlertDescription>
        </Alert>
      ) : null}

      {query.isSuccess ? (
        projects.length === 0 ? (
          <Card>
            <CardDescription>{t("empty")}</CardDescription>
          </Card>
        ) : (
          <>
            <div className="hidden md:block">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-card-border text-start text-text-tertiary">
                    <th className="py-2 pe-4">{t("reference")}</th>
                    <th className="py-2 pe-4">{t("name")}</th>
                    <th className="py-2 pe-4">{t("client")}</th>
                    <th className="py-2 pe-4">{t("stage")}</th>
                    <th className="py-2">{tCommon("status")}</th>
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
      <td className="py-3 pe-4">
        <Link
          href={`/projects/${project.reference}`}
          className="font-medium text-brand-500"
        >
          {project.reference}
        </Link>
      </td>
      <td className="py-3 pe-4">{project.name}</td>
      <td className="py-3 pe-4">{project.client_name ?? "—"}</td>
      <td className="py-3 pe-4">{formatEnumLabel(project.stage)}</td>
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
              {formatEnumLabel(project.stage)}
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
        {formatEnumLabel(project.status)}
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
