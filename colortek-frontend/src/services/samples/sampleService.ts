import { axiosInstance } from "@/config/axios";
import { unwrapData, type PaginatedResponse } from "@/types/api";
import { isSample, isSampleChain, type ClientDecisionPayload, type ModificationRequestPayload, type Sample, type SampleChain, type StartSamplePayload } from "@/types/samples";
export interface SampleListParams { page?: number; per_page?: number; status?: string; search?: string; }
export async function getSamples(params?: SampleListParams): Promise<PaginatedResponse<Sample>> {
  const r = await axiosInstance.get<unknown>("/samples", { params: params as Record<string, string | number | boolean | undefined> });
  if (!r || typeof r !== "object" || !Array.isArray((r as PaginatedResponse<Sample>).data)) throw new Error("Invalid samples list");
  return r as PaginatedResponse<Sample>;
}
export async function getSample(reference: string){ const d = unwrapData<unknown>(await axiosInstance.get(`/samples/${encodeURIComponent(reference)}`)); if(!isSample(d)) throw new Error("Invalid sample"); return d; }
export async function getSampleChain(reference: string){ const d = unwrapData<unknown>(await axiosInstance.get(`/samples/${encodeURIComponent(reference)}/chain`)); if(!isSampleChain(d)) throw new Error("Invalid chain"); return d; }
export async function startSample(p: StartSamplePayload){ const d = unwrapData<unknown>(await axiosInstance.post("/samples", p)); if(!isSample(d)) throw new Error("Invalid"); return d; }
export async function requestModification(ref: string, p: ModificationRequestPayload){ const d = unwrapData<unknown>(await axiosInstance.post(`/samples/${encodeURIComponent(ref)}/modification`, p)); if(!isSample(d)) throw new Error("Invalid"); return d; }
export async function downloadApprovalForm(ref: string){ return axiosInstance.postBlob(`/samples/${encodeURIComponent(ref)}/approval-form`); }
export function triggerPdfDownload(blob: Blob, name: string){ const u = URL.createObjectURL(blob); const a = document.createElement("a"); a.href=u; a.download=name; a.click(); URL.revokeObjectURL(u); }
export async function recordClientDecision(ref: string, p: ClientDecisionPayload){ const d = unwrapData<unknown>(await axiosInstance.post(`/samples/${encodeURIComponent(ref)}/client-decision`, p)); if(!isSample(d)) throw new Error("Invalid"); return d; }
