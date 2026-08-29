"use client";

import { ApiError } from "@/config/axios";
import { Button } from "@/components/tailgrids/core/button";
import { CardTitle } from "@/components/tailgrids/core/card";
import { Label } from "@/components/tailgrids/core/label";
import { usePermissions } from "@/hooks/use-permissions";
import { queryKeys } from "@/lib/queryKeys";
import { getUserOptions } from "@/services/optionsService";
import { updateProject } from "@/services/projectService";
import type { ProjectDetail } from "@/types/projects";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { useState } from "react";

const selectClass =
  "mt-1 w-full rounded-lg border border-card-border bg-card-bg px-3 py-2.5 text-sm text-text-primary";

/**
 * Who owns this project: the salesperson who owns the client relationship, and
 * the project manager once execution starts. `specs/03-data-model.md`
 * "Projects" — `sales_user_id` and `responsible_user_id`.
 *
 * Work on the project itself — workshop, tinting, site — is assigned per task
 * (claim, or Reassign on the task detail screen), not staffed here, because a
 * project runs several independent task queues rather than one team roster.
 */
export default function ProjectTeamPanel({
  project,
}: {
  project: ProjectDetail;
}) {
  const t = useTranslations("projects");
  const tCommon = useTranslations("common");
  const { can } = usePermissions();
  const queryClient = useQueryClient();

  const [salesUserId, setSalesUserId] = useState(
    String(project.sales_user?.id ?? ""),
  );
  const [responsibleUserId, setResponsibleUserId] = useState(
    String(project.responsible_user?.id ?? ""),
  );
  const [error, setError] = useState<string | null>(null);

  const canAssign = can("project.update");

  const usersQuery = useQuery({
    queryKey: queryKeys.options.users(),
    queryFn: () => getUserOptions(),
    enabled: canAssign,
  });

  const mutation = useMutation({
    mutationFn: () =>
      updateProject(project.id, {
        sales_user_id: salesUserId === "" ? null : Number(salesUserId),
        responsible_user_id:
          responsibleUserId === "" ? null : Number(responsibleUserId),
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["projects"] });
      setError(null);
    },
    onError: (err: unknown) => setError(getErrorMessage(err)),
  });

  const dirty =
    salesUserId !== String(project.sales_user?.id ?? "") ||
    responsibleUserId !== String(project.responsible_user?.id ?? "");

  if (!canAssign) {
    return (
      <div>
        <CardTitle className="text-base">{t("team")}</CardTitle>
        <p className="mt-2 text-sm text-text-secondary">
          {t("salesperson")}: {project.sales_user?.name ?? "—"}
        </p>
        <p className="mt-1 text-sm text-text-secondary">
          {t("responsibleUser")}:{" "}
          {project.responsible_user?.name ?? t("unassigned")}
        </p>
      </div>
    );
  }

  return (
    <div>
      <CardTitle className="text-base">{t("team")}</CardTitle>
      <p className="mt-1 text-xs text-text-tertiary">{t("teamHint")}</p>

      {error ? (
        <p className="mt-2 rounded-lg bg-error-50 px-3 py-2 text-sm text-error-600">
          {error}
        </p>
      ) : null}

      <div className="mt-3 grid gap-3 sm:grid-cols-2">
        <div>
          <Label htmlFor="sales-user">{t("salesperson")}</Label>
          <select
            id="sales-user"
            value={salesUserId}
            onChange={(e) => setSalesUserId(e.target.value)}
            className={selectClass}
          >
            <option value="">{t("unassigned")}</option>
            {(usersQuery.data ?? []).map((user) => (
              <option key={user.id} value={user.id}>
                {user.label}
              </option>
            ))}
          </select>
        </div>

        <div>
          <Label htmlFor="responsible-user">{t("responsibleUser")}</Label>
          <select
            id="responsible-user"
            value={responsibleUserId}
            onChange={(e) => setResponsibleUserId(e.target.value)}
            className={selectClass}
          >
            <option value="">{t("unassigned")}</option>
            {(usersQuery.data ?? []).map((user) => (
              <option key={user.id} value={user.id}>
                {user.label}
              </option>
            ))}
          </select>
        </div>
      </div>

      <Button
        variant="primary"
        appearance="fill"
        className="mt-3"
        isDisabled={!dirty || mutation.isPending}
        onPress={() => mutation.mutate()}
      >
        {mutation.isPending ? tCommon("saving") : tCommon("save")}
      </Button>
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
