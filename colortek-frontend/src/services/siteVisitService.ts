import { axiosInstance } from "@/config/axios";
import { unwrapData, type ApiEnvelope } from "@/types/api";
import type { SiteChecklistItem, SiteMeasurementRow, SiteVisitAnswer, SiteVisitDetail } from "@/types/siteVisit";

export async function getSiteVisit(id: number): Promise<SiteVisitDetail> {
  const response = await axiosInstance.get<ApiEnvelope<SiteVisitDetail>>(`/site-visits/${id}`);
  return unwrapData(response);
}

export async function getSiteChecklistItems(): Promise<SiteChecklistItem[]> {
  const response = await axiosInstance.get<{ data: SiteChecklistItem[] }>("/site-checklist-items");
  return response.data;
}

export async function patchSiteVisitDraft(id: number, payload: Record<string, unknown>): Promise<SiteVisitDetail> {
  const response = await axiosInstance.patch<ApiEnvelope<SiteVisitDetail>>(`/site-visits/${id}`, payload);
  return unwrapData(response);
}

export async function saveSiteMeasurements(id: number, rows: SiteMeasurementRow[], idempotencyKey?: string): Promise<SiteVisitDetail> {
  const headers: Record<string, string> = {};
  if (idempotencyKey) headers["Idempotency-Key"] = idempotencyKey;
  const response = await axiosInstance.post<ApiEnvelope<SiteVisitDetail>>(
    `/site-visits/${id}/measurements`,
    { rows },
    Object.keys(headers).length > 0 ? { headers } : undefined,
  );
  return unwrapData(response);
}

export async function submitSiteVisit(
  id: number,
  answers: SiteVisitAnswer[],
  signedAttachmentId: number,
  clientSignatoryName?: string,
): Promise<{ visit: SiteVisitDetail; humidityWarning: boolean }> {
  const response = await axiosInstance.post<ApiEnvelope<SiteVisitDetail> & { meta?: { humidity_warning?: boolean } }>(
    `/site-visits/${id}/submit`,
    { answers, signed_attachment_id: signedAttachmentId, client_signatory_name: clientSignatoryName },
  );
  return { visit: unwrapData(response), humidityWarning: response.meta?.humidity_warning === true };
}

export function siteVisitPdfUrl(id: number): string {
  const base = process.env.NEXT_PUBLIC_API_URL ?? "http://127.0.0.1:8000/api/v1";
  return `${base}/site-visits/${id}/pdf`;
}
