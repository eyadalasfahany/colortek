"use client";

import { Alert, AlertDescription } from "@/components/tailgrids/core/alert";
import { Button } from "@/components/tailgrids/core/button";
import { Card, CardDescription, CardTitle } from "@/components/tailgrids/core/card";
import { Input } from "@/components/tailgrids/core/input";
import { Label } from "@/components/tailgrids/core/label";
import { Skeleton } from "@/components/tailgrids/core/skeleton";
import { TextArea } from "@/components/tailgrids/core/text-area";
import { clearSiteVisitDraft, loadSiteVisitDraft, saveSiteVisitDraft } from "@/lib/siteVisitDraftStore";
import { enqueuePhoto, flushPhotoQueue, isOnline } from "@/lib/offlinePhotoQueue";
import { queryKeys } from "@/lib/queryKeys";
import { uploadAttachment } from "@/services/attachmentService";
import {
  getSiteChecklistItems,
  getSiteVisit,
  patchSiteVisitDraft,
  saveSiteMeasurements,
  siteVisitPdfUrl,
  submitSiteVisit,
} from "@/services/siteVisitService";
import type { SiteChecklistItem, SiteMeasurementRow, SiteVisitAnswer, SiteVisitDetail } from "@/types/siteVisit";
import { cn } from "@/utils/cn";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect, useMemo, useState } from "react";

const emptyRow = (sortOrder: number, page = 1, line = 1): SiteMeasurementRow => ({
  page_number: page,
  line_number: line,
  element_name: "",
  height_m: "",
  length_m: "",
  width_m: "",
  thickness_m: "",
  diameter_m: "",
  other_note: "",
  area_sqm: null,
  verified: false,
  sort_order: sortOrder,
  deductions: [{ count: 1, length_m: null, width_m: null, sign: "subtract", label: "" }],
});

interface DraftState {
  header: Record<string, string>;
  rows: SiteMeasurementRow[];
  answers: Record<string, SiteVisitAnswer>;
  clientSignatoryName: string;
  signedAttachmentId: number | null;
}

export default function SiteVisitForm({ visitId }: { visitId: number }) {
  const queryClient = useQueryClient();
  const [step, setStep] = useState(0);
  const [syncLabel, setSyncLabel] = useState("Loading…");
  const [error, setError] = useState<string | null>(null);
  const [lastWidth, setLastWidth] = useState("");
  const [draft, setDraft] = useState<DraftState | null>(null);

  const visitQuery = useQuery({ queryKey: queryKeys.siteVisits.detail(visitId), queryFn: () => getSiteVisit(visitId) });
  const checklistQuery = useQuery({ queryKey: queryKeys.siteVisits.checklist(), queryFn: getSiteChecklistItems });

  useEffect(() => {
    if (!visitQuery.data || !checklistQuery.data || draft) return;
    void (async () => {
      const local = await loadSiteVisitDraft<DraftState>(visitId);
      const visit = visitQuery.data;
      const answers: Record<string, SiteVisitAnswer> = {};
      for (const item of checklistQuery.data) {
        const existing = visit.answers?.find((a) => a.checklist_item.code === item.code);
        answers[item.code] = {
          code: item.code,
          value: (existing?.answer_value?.value as string | number | boolean | null) ?? null,
          note: existing?.note ?? "",
        };
      }
      const base: DraftState = {
        header: {
          visited_on: visit.visited_on,
          project_name_on_form: visit.project_name_on_form,
          address_on_form: visit.address_on_form ?? "",
          quotation_number_on_form: visit.quotation_number_on_form ?? "",
          client_reference_note: visit.client_reference_note ?? "",
        },
        rows: visit.measurements?.length
          ? visit.measurements.map((m, i) => ({
              ...m,
              element_name: m.element_name ?? "",
              height_m: String(m.height_m ?? ""),
              length_m: String(m.length_m ?? ""),
              width_m: String(m.width_m ?? ""),
              thickness_m: String(m.thickness_m ?? ""),
              diameter_m: String(m.diameter_m ?? ""),
              other_note: m.other_note ?? "",
              deductions: m.deductions?.length ? m.deductions : emptyRow(i).deductions,
            }))
          : [emptyRow(0)],
        answers,
        clientSignatoryName: visit.client_signatory_name ?? "",
        signedAttachmentId: null,
      };
      setDraft(local?.draft ?? base);
      setSyncLabel(local ? `Saved on this device ${new Date(local.savedAt).toLocaleTimeString()}` : "Synced with server");
    })();
  }, [visitQuery.data, checklistQuery.data, visitId, draft]);

  const persistLocal = async (next: DraftState) => {
    setDraft(next);
    await saveSiteVisitDraft(visitId, next);
    setSyncLabel(`Saved on this device ${new Date().toLocaleTimeString()}`);
  };

  const saveDraftMutation = useMutation({
    mutationFn: async () => {
      if (!draft) return;
      if (!isOnline()) {
        await persistLocal(draft);
        return;
      }
      await patchSiteVisitDraft(visitId, { ...draft.header, client_signatory_name: draft.clientSignatoryName });
      await saveSiteMeasurements(visitId, draft.rows, `visit-${visitId}-${Date.now()}`);
      setSyncLabel("Synced with server");
    },
  });

  const submitMutation = useMutation({
    mutationFn: async () => {
      if (!draft) throw new Error("Draft missing");
      if (!isOnline()) throw new Error("Submit requires a connection. Your draft is kept on this device.");
      if (!draft.signedAttachmentId) throw new Error("Signed scan is required.");
      const result = await submitSiteVisit(visitId, Object.values(draft.answers), draft.signedAttachmentId, draft.clientSignatoryName);
      await clearSiteVisitDraft(visitId);
      await queryClient.invalidateQueries({ queryKey: queryKeys.siteVisits.detail(visitId) });
      return result;
    },
    onError: (e) => setError(e instanceof Error ? e.message : "Submit failed"),
  });

  const pageCount = useMemo(() => new Set((draft?.rows ?? []).map((r) => r.page_number)).size, [draft?.rows]);

  if (visitQuery.isLoading || checklistQuery.isLoading || !draft) {
    return <Skeleton className="mx-4 mt-6 h-96 w-full max-w-4xl" />;
  }

  const visit = visitQuery.data as SiteVisitDetail;
  if (visit.is_submitted) {
    return (
      <div className="mx-auto max-w-4xl px-4 pt-6">
        <Card>
          <CardTitle>Visit submitted</CardTitle>
          <CardDescription>{visit.reference} is read-only.</CardDescription>
          <a href={siteVisitPdfUrl(visitId)} className="mt-4 inline-block text-brand-500" target="_blank" rel="noreferrer">Print PDF</a>
        </Card>
      </div>
    );
  }

  const checklist = checklistQuery.data as SiteChecklistItem[];

  return (
    <div className="mx-auto max-w-4xl px-4 pb-24 pt-6">
      <p className="mb-4 text-sm text-text-secondary">{syncLabel}</p>
      <div className="mb-6 flex flex-wrap gap-2 text-sm">
        {["Measurements", "Site condition", "Review"].map((label, index) => (
          <span key={label} className={cn("rounded-full px-3 py-1", step === index ? "bg-brand-500 text-white" : "bg-surface-secondary text-text-secondary")}>
            {index + 1} · {label}
          </span>
        ))}
      </div>

      {step === 0 ? (
        <Card className="space-y-4 p-4">
          <CardTitle>Measurements</CardTitle>
          <p className="text-sm text-text-secondary">{draft.rows.length} rows · {pageCount} page(s)</p>
          <div className="grid gap-3 sm:grid-cols-2">
            {Object.entries({ visited_on: "Date", project_name_on_form: "Project name", address_on_form: "Address", quotation_number_on_form: "Quotation", client_reference_note: "Reference" }).map(([key, label]) => (
              <div key={key} className="flex flex-col gap-1">
                <Label>{label}</Label>
                <Input value={draft.header[key] ?? ""} onChange={(e) => void persistLocal({ ...draft, header: { ...draft.header, [key]: e.target.value } })} />
              </div>
            ))}
          </div>
          {draft.rows.map((row, index) => (
            <div key={index} className="rounded-lg border border-card-border p-3">
              <div className="grid gap-2 sm:grid-cols-3">
                <Input placeholder="Element" value={row.element_name} onChange={(e) => { const rows = [...draft.rows]; rows[index] = { ...row, element_name: e.target.value }; void persistLocal({ ...draft, rows }); }} />
                <Input inputMode="decimal" placeholder="Height" value={row.height_m} onChange={(e) => { const rows = [...draft.rows]; rows[index] = { ...row, height_m: e.target.value }; void persistLocal({ ...draft, rows }); }} />
                <Input inputMode="decimal" placeholder="Length" value={row.length_m} onChange={(e) => { const rows = [...draft.rows]; rows[index] = { ...row, length_m: e.target.value }; void persistLocal({ ...draft, rows }); }} />
                <Input inputMode="decimal" placeholder="Width" value={row.width_m || lastWidth} onChange={(e) => { setLastWidth(e.target.value); const rows = [...draft.rows]; rows[index] = { ...row, width_m: e.target.value }; void persistLocal({ ...draft, rows }); }} />
              </div>
              <p className="mt-2 text-xs text-text-tertiary">Area (m²): calculated later</p>
            </div>
          ))}
          <div>
            <Button variant="ghost" appearance="outline" onPress={() => void persistLocal({ ...draft, rows: [...draft.rows, emptyRow(draft.rows.length)] })}>Add row</Button>
            <Button variant="primary" appearance="fill" className="ms-2" onPress={() => void saveDraftMutation.mutateAsync().then(() => setStep(1))}>Continue</Button>
          </div>
        </Card>
      ) : null}

      {step === 1 ? (
        <Card className="space-y-4 p-4">
          <CardTitle>Site condition</CardTitle>
          {checklist.map((item) => (
            <div key={item.code} className="rounded-lg border border-card-border p-3">
              <p className="font-medium" dir="rtl">{item.label_ar}</p>
              <p className="text-sm text-text-secondary">{item.label_en}</p>
              {item.answer_type === "yes_no" ? (
                <div className="mt-2 flex gap-2">
                  {(["yes", "no"] as const).map((v) => (
                    <Button key={v} variant={draft.answers[item.code]?.value === (v === "yes") ? "primary" : "ghost"} appearance="fill" onPress={() => void persistLocal({ ...draft, answers: { ...draft.answers, [item.code]: { code: item.code, value: v === "yes", note: draft.answers[item.code]?.note ?? "" } } })}>
                      {v === "yes" ? "Yes" : "No"}
                    </Button>
                  ))}
                </div>
              ) : (
                <Input className="mt-2" inputMode={item.answer_type === "percentage" ? "decimal" : "text"} value={String(draft.answers[item.code]?.value ?? "")} onChange={(e) => void persistLocal({ ...draft, answers: { ...draft.answers, [item.code]: { code: item.code, value: e.target.value, note: draft.answers[item.code]?.note ?? "" } } })} />
              )}
              <TextArea className="mt-2 min-h-16" placeholder="Notes" value={draft.answers[item.code]?.note ?? ""} onChange={(e) => void persistLocal({ ...draft, answers: { ...draft.answers, [item.code]: { code: item.code, value: draft.answers[item.code]?.value ?? null, note: e.target.value } } })} />
              {item.is_readiness_critical && draft.answers[item.code]?.value === false ? (
                <Alert status="warning" className="mt-2"><AlertDescription>This will mark the site Not Ready. Site work will be held.</AlertDescription></Alert>
              ) : null}
            </div>
          ))}
          <div>
            <Button variant="ghost" appearance="outline" onPress={() => setStep(0)}>Back</Button>
            <Button variant="primary" appearance="fill" className="ms-2" onPress={() => setStep(2)}>Review</Button>
          </div>
        </Card>
      ) : null}

      {step === 2 ? (
        <Card className="space-y-4 p-4">
          <CardTitle>Review & submit</CardTitle>
          <CardDescription>{draft.rows.length} rows · signed scan required</CardDescription>
          <div className="flex flex-col gap-1.5">
            <Label>Client signatory name</Label>
            <Input value={draft.clientSignatoryName} onChange={(e) => void persistLocal({ ...draft, clientSignatoryName: e.target.value })} />
          </div>
          <label className="inline-flex cursor-pointer text-sm text-brand-500">
            <input type="file" accept="image/*,application/pdf" className="hidden" onChange={async (e) => {
              const file = e.target.files?.[0];
              if (!file) return;
              setError(null);
              try {
                if (!isOnline()) {
                  enqueuePhoto(file, "site_report_signed");
                  setError("Photo queued — connect to upload before submit.");
                  return;
                }
                const id = (await uploadAttachment(file, "site_report_signed")).id;
                void persistLocal({ ...draft, signedAttachmentId: id });
              } catch {
                setError("Upload failed");
              }
            }} />
            Upload signed scan
          </label>
          {submitMutation.data?.humidityWarning ? (
            <Alert status="warning"><AlertDescription>Humidity above threshold — recorded with warning.</AlertDescription></Alert>
          ) : null}
          {error ? <Alert status="error"><AlertDescription>{error}</AlertDescription></Alert> : null}
          <div>
            <Button variant="ghost" appearance="outline" onPress={() => setStep(1)}>Back</Button>
            <Button variant="primary" appearance="fill" className="ms-2" isDisabled={submitMutation.isPending} onPress={() => void submitMutation.mutateAsync()}>Submit visit</Button>
          </div>
        </Card>
      ) : null}
    </div>
  );
}
