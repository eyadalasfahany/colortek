"use client";

import { ApiError } from "@/config/axios";
import { Badge } from "@/components/tailgrids/core/badge";
import { Button } from "@/components/tailgrids/core/button";
import { Label } from "@/components/tailgrids/core/label";
import { usePermissions } from "@/hooks/use-permissions";
import { queryKeys } from "@/lib/queryKeys";
import { getEnumOptions } from "@/services/enumService";
import { updateProject } from "@/services/projectService";
import type { ProjectDetail, WorkflowNextAction } from "@/types/projects";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { useState } from "react";

interface ProjectStageControlsProps {
  project: ProjectDetail;
  currentStageLabel?: string | null;
  nextAction?: WorkflowNextAction | null;
}

const selectClass =
  "mt-1 w-full rounded-lg border border-card-border bg-card-bg px-3 py-2.5 text-sm text-text-primary";

/**
 * Moves a project between stages and statuses. Both option lists come from
 * `GET /enums/*` rather than being hardcoded, so they always match the backend.
 */
export default function ProjectStageControls({
  project,
  currentStageLabel,
  nextAction,
}: ProjectStageControlsProps) {
  const t = useTranslations("projects");
  const tCommon = useTranslations("common");
  const { can } = usePermissions();
  const queryClient = useQueryClient();

  const [stage, setStage] = useState(project.stage);
  const [status, setStatus] = useState(project.status ?? "");
  const [error, setError] = useState<string | null>(null);

  const canChange = can("project.change_stage") || can("project.update");

  const stagesQuery = useQuery({
    queryKey: queryKeys.enums.options("project_stage"),
    queryFn: () => getEnumOptions("project_stage"),
    enabled: canChange,
  });

  const statusesQuery = useQuery({
    queryKey: queryKeys.enums.options("project_status"),
    queryFn: () => getEnumOptions("project_status"),
    enabled: canChange,
  });

  const mutation = useMutation({
    mutationFn: (body: { stage?: string; status?: string }) =>
      updateProject(project.id, body),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["projects"] });
      setError(null);
    },
    onError: (err: unknown) => setError(getErrorMessage(err)),
  });

  const dirty = stage !== project.stage || status !== (project.status ?? "");

  return (
    <div className="space-y-4">
      <div>
        <p className="text-sm text-text-secondary">{t("currentWorkflow")}</p>
        {nextAction ? (
          <div className="mt-1 flex flex-wrap items-center gap-2">
            <Badge color="primary" size="sm">
              {currentStageLabel ?? project.stage}
            </Badge>
            <span className="text-sm text-text-primary">
              {nextAction.title}
            </span>
            <span className="text-sm text-text-secondary">
              · {nextAction.department} · {nextAction.holder}
            </span>
            {nextAction.is_overdue ? (
              <Badge color="error" size="sm">
                {t("overdue")}
              </Badge>
            ) : null}
          </div>
        ) : (
          <p className="mt-1 text-sm text-text-tertiary">{t("noWorkflow")}</p>
        )}
      </div>

      {canChange ? (
        <>
          {error ? (
            <p className="rounded-lg bg-error-50 px-3 py-2 text-sm text-error-600">
              {error}
            </p>
          ) : null}

          <div className="grid gap-3 sm:grid-cols-2">
            <div>
              <Label htmlFor="project-stage">{t("changeStage")}</Label>
              <select
                id="project-stage"
                value={stage}
                onChange={(e) => setStage(e.target.value)}
                className={selectClass}
              >
                {(stagesQuery.data ?? []).map((option) => (
                  <option key={option.value} value={option.value}>
                    {option.label}
                  </option>
                ))}
              </select>
            </div>

            <div>
              <Label htmlFor="project-status">{t("changeStatus")}</Label>
              <select
                id="project-status"
                value={status}
                onChange={(e) => setStatus(e.target.value)}
                className={selectClass}
              >
                {(statusesQuery.data ?? []).map((option) => (
                  <option key={option.value} value={option.value}>
                    {option.label}
                  </option>
                ))}
              </select>
            </div>
          </div>

          <Button
            variant="primary"
            appearance="fill"
            isDisabled={!dirty || mutation.isPending}
            onPress={() => {
              const body: { stage?: string; status?: string } = {};
              if (stage !== project.stage) body.stage = stage;
              if (status !== (project.status ?? "")) body.status = status;
              mutation.mutate(body);
            }}
          >
            {mutation.isPending ? tCommon("saving") : tCommon("save")}
          </Button>
        </>
      ) : null}
    </div>
  );
}

function getErrorMessage(error: unknown): string {
  if (error instanceof ApiError) {
    const first = error.errors
      ? Object.values(error.errors)[0]?.[0]
      : undefined;
    return first ?? error.message;
  }
  if (error instanceof Error) {
    return error.message;
  }
  return "Something went wrong.";
}
