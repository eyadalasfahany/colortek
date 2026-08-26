"use client";

import { Alert, AlertDescription, AlertTitle } from "@/components/tailgrids/core/alert";
import { Button } from "@/components/tailgrids/core/button";
import { Card, CardDescription, CardTitle } from "@/components/tailgrids/core/card";
import { Label } from "@/components/tailgrids/core/label";
import { TextArea } from "@/components/tailgrids/core/text-area";
import { queryKeys } from "@/lib/queryKeys";
import { overrideSiteBlock } from "@/services/taskService";
import type { TaskDetail } from "@/types/api";
import type { CorrectiveActionSubjectContext, SiteBlockContext, SiteVisitSubjectContext } from "@/types/siteVisit";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { Link } from "@/i18n/navigation";

export function SiteBlockPanel({ task, block }: { task: TaskDetail; block: SiteBlockContext }) {
  const [reason, setReason] = useState("");
  const queryClient = useQueryClient();
  const mutation = useMutation({
    mutationFn: () => overrideSiteBlock(task.id, reason),
    onSuccess: async () => queryClient.invalidateQueries({ queryKey: queryKeys.tasks.detail(task.id) }),
  });

  return (
    <Card className="mb-4 border-status-danger/30 bg-surface-secondary">
      <CardTitle className="text-status-danger">Held — the site is not ready</CardTitle>
      <CardDescription className="mt-2 text-text-primary">
        Site visit {block.visit_reference}, {block.visited_on}
        {block.summary ? `: ${block.summary}` : ""}
      </CardDescription>
      <p className="mt-2 text-sm text-text-secondary">{block.open_corrective_count} corrective action(s) open.</p>
      {task.status === "pending" ? (
        <div className="mt-4 space-y-2">
          <Label htmlFor="override-reason">Override reason</Label>
          <TextArea id="override-reason" value={reason} onChange={(e) => setReason(e.target.value)} className="min-h-20" />
          <Button variant="primary" appearance="outline" isDisabled={!reason.trim() || mutation.isPending} onPress={() => mutation.mutate()}>
            Override block
          </Button>
        </div>
      ) : null}
    </Card>
  );
}

export function SiteConductVisitPanel({ subject }: { subject: SiteVisitSubjectContext }) {
  return (
    <Card className="mb-4">
      <CardTitle>Conduct site visit</CardTitle>
      <CardDescription className="mt-2">{subject.reference} · visit #{subject.visit_number}</CardDescription>
      {!subject.is_submitted ? (
        <Link href={`/site-visits/${subject.id}/edit`} className="mt-4 inline-flex text-sm text-brand-500 hover:text-brand-600">
          Open site visit form
        </Link>
      ) : (
        <p className="mt-2 text-sm text-text-secondary">Visit submitted and locked.</p>
      )}
    </Card>
  );
}

export function SiteReadinessPanel({
  subject,
  readiness,
  summary,
  onReadinessChange,
  onSummaryChange,
}: {
  subject: SiteVisitSubjectContext;
  readiness: string;
  summary: string;
  onReadinessChange: (value: string) => void;
  onSummaryChange: (value: string) => void;
}) {
  return (
    <Card className="mb-4">
      <CardTitle>Set site readiness</CardTitle>
      <CardDescription className="mt-2">Visit {subject.reference}</CardDescription>
      <div className="mt-4 flex gap-3">
        <Button variant={readiness === "ready" ? "primary" : "ghost"} appearance="fill" isDisabled={subject.has_critical_failures} onPress={() => onReadinessChange("ready")}>Ready</Button>
        <Button variant={readiness === "not_ready" ? "primary" : "ghost"} appearance="fill" onPress={() => onReadinessChange("not_ready")}>Not ready</Button>
      </div>
      {subject.has_critical_failures ? (
        <Alert status="warning" className="mt-4"><AlertDescription>Critical checklist failures — Ready is disabled.</AlertDescription></Alert>
      ) : null}
      {readiness === "not_ready" ? (
        <div className="mt-4 flex flex-col gap-1.5">
          <Label htmlFor="readiness-summary">Summary *</Label>
          <TextArea id="readiness-summary" value={summary} onChange={(e) => onSummaryChange(e.target.value)} className="min-h-24" />
        </div>
      ) : null}
    </Card>
  );
}

export function CorrectiveActionPanel({ subject }: { subject: CorrectiveActionSubjectContext }) {
  return (
    <Card className="mb-4">
      <CardTitle>Corrective action</CardTitle>
      <CardDescription className="mt-2">{subject.description}</CardDescription>
      <p className="mt-2 text-sm text-text-secondary">Responsible: {subject.responsible_party}</p>
    </Card>
  );
}

export function SiteReinspectionPanel({ subject }: { subject: SiteVisitSubjectContext }) {
  return (
    <Card className="mb-4">
      <CardTitle>Re-inspection</CardTitle>
      <CardDescription className="mt-2">Complete to open visit #{subject.visit_number + 1} with prefilled measurements.</CardDescription>
    </Card>
  );
}
