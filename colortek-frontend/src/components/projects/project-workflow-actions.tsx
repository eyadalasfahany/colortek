"use client";

import { ApiError } from "@/config/axios";
import { Button } from "@/components/tailgrids/core/button";
import {
  Dialog,
  DialogBody,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/tailgrids/core/dialog";
import { Input } from "@/components/tailgrids/core/input";
import { Label } from "@/components/tailgrids/core/label";
import { usePermissions } from "@/hooks/use-permissions";
import {
  startPayment,
  startSample,
  startSiteVisit,
} from "@/services/projectService";
import type { ProjectDetail } from "@/types/projects";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { useState } from "react";

interface ProjectWorkflowActionsProps {
  project: ProjectDetail;
}

/**
 * Starts a workflow instance. `specs/05-workflow-engine.md` §12: a project runs
 * several small workflows, each attached to the thing it is about — one
 * payment_cycle per installment, one sample_request per sample, one site_visit
 * per visit. Changing the project stage deliberately creates no tasks; these
 * actions are what do.
 */
export default function ProjectWorkflowActions({
  project,
}: ProjectWorkflowActionsProps) {
  const t = useTranslations("projects");
  const tCommon = useTranslations("common");
  const { can } = usePermissions();
  const queryClient = useQueryClient();

  const [paymentOpen, setPaymentOpen] = useState(false);
  const [sampleOpen, setSampleOpen] = useState(false);
  const [installment, setInstallment] = useState("1");
  const [color, setColor] = useState("");
  const [texture, setTexture] = useState("");
  const [neededBy, setNeededBy] = useState("");
  const [error, setError] = useState<string | null>(null);

  function refresh() {
    queryClient.invalidateQueries({ queryKey: ["projects"] });
    queryClient.invalidateQueries({ queryKey: ["tasks"] });
    setError(null);
  }

  const onError = (err: unknown) => setError(getErrorMessage(err));

  const paymentMutation = useMutation({
    mutationFn: () => startPayment(project.id, Number(installment)),
    onSuccess: () => {
      refresh();
      setPaymentOpen(false);
    },
    onError,
  });

  const sampleMutation = useMutation({
    mutationFn: () =>
      startSample({
        client_id: project.client?.id ?? 0,
        project_id: project.id,
        color,
        texture: texture || null,
        needed_by: neededBy || null,
      }),
    onSuccess: () => {
      refresh();
      setSampleOpen(false);
      setColor("");
      setTexture("");
      setNeededBy("");
    },
    onError,
  });

  const siteVisitMutation = useMutation({
    mutationFn: () => startSiteVisit(project.id),
    onSuccess: refresh,
    onError,
  });

  const canPayment = can("payment.confirm") || can("payment.view");
  const canSample = can("sample.create");
  const canSiteVisit = can("site.visit_create");

  if (!canPayment && !canSample && !canSiteVisit) {
    return null;
  }

  const pending =
    paymentMutation.isPending ||
    sampleMutation.isPending ||
    siteVisitMutation.isPending;

  return (
    <div className="space-y-3">
      <p className="text-sm text-text-secondary">{t("startWorkLabel")}</p>
      <p className="text-xs text-text-tertiary">{t("startWorkHint")}</p>

      {error ? (
        <p className="rounded-lg bg-error-50 px-3 py-2 text-sm text-error-600">
          {error}
        </p>
      ) : null}

      <div className="flex flex-wrap gap-2">
        {canPayment ? (
          <Button
            variant="ghost"
            appearance="outline"
            isDisabled={pending}
            onPress={() => setPaymentOpen(true)}
          >
            {t("startPayment")}
          </Button>
        ) : null}
        {canSample ? (
          <Button
            variant="ghost"
            appearance="outline"
            isDisabled={pending || !project.client?.id}
            onPress={() => setSampleOpen(true)}
          >
            {t("startSample")}
          </Button>
        ) : null}
        {canSiteVisit ? (
          <Button
            variant="ghost"
            appearance="outline"
            isDisabled={pending}
            onPress={() => siteVisitMutation.mutate()}
          >
            {t("startSiteVisit")}
          </Button>
        ) : null}
      </div>

      <Dialog isOpen={paymentOpen} onOpenChange={setPaymentOpen}>
        <DialogHeader>
          <DialogTitle>{t("startPayment")}</DialogTitle>
          <DialogDescription>{t("startPaymentDescription")}</DialogDescription>
        </DialogHeader>
        <DialogBody className="py-0">
          <Label htmlFor="installment">{t("installment")}</Label>
          <Input
            id="installment"
            value={installment}
            onChange={(e) => setInstallment(e.target.value)}
            inputMode="numeric"
            className="mt-1 w-full px-3 py-2.5 text-sm"
          />
          <Button
            variant="primary"
            appearance="fill"
            className="mt-3"
            isDisabled={Number(installment) < 1 || pending}
            onPress={() => paymentMutation.mutate()}
          >
            {pending ? tCommon("saving") : t("startPayment")}
          </Button>
        </DialogBody>
      </Dialog>

      <Dialog isOpen={sampleOpen} onOpenChange={setSampleOpen}>
        <DialogHeader>
          <DialogTitle>{t("startSample")}</DialogTitle>
          <DialogDescription>{t("startSampleDescription")}</DialogDescription>
        </DialogHeader>
        <DialogBody className="space-y-3 py-0">
          <div>
            <Label htmlFor="sample-color">{t("sampleColor")}</Label>
            <Input
              id="sample-color"
              value={color}
              onChange={(e) => setColor(e.target.value)}
              className="mt-1 w-full px-3 py-2.5 text-sm"
            />
          </div>
          <div>
            <Label htmlFor="sample-texture">{t("sampleTexture")}</Label>
            <Input
              id="sample-texture"
              value={texture}
              onChange={(e) => setTexture(e.target.value)}
              className="mt-1 w-full px-3 py-2.5 text-sm"
            />
          </div>
          <div>
            <Label htmlFor="sample-needed-by">{t("sampleNeededBy")}</Label>
            <Input
              id="sample-needed-by"
              type="date"
              value={neededBy}
              onChange={(e) => setNeededBy(e.target.value)}
              className="mt-1 w-full px-3 py-2.5 text-sm"
            />
          </div>
          <Button
            variant="primary"
            appearance="fill"
            isDisabled={!color.trim() || pending}
            onPress={() => sampleMutation.mutate()}
          >
            {pending ? tCommon("saving") : t("startSample")}
          </Button>
        </DialogBody>
      </Dialog>
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
