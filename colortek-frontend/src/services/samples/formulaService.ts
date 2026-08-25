import { axiosInstance } from "@/config/axios";
import { unwrapData } from "@/types/api";
import type { AuthorFormulaPayload, Formula, PatchFormulaPayload, RegisterFormulaPayload } from "@/types/samples";
const isFormula = (v: unknown): v is Formula => typeof v === "object" && v !== null && typeof (v as Formula).id === "number";
export async function getFormulas(ref: string){ const d = unwrapData<unknown>(await axiosInstance.get(`/samples/${encodeURIComponent(ref)}/formulas`)); return Array.isArray(d) ? d.filter(isFormula) : []; }
export async function authorFormula(ref: string, p: AuthorFormulaPayload){ const d = unwrapData<unknown>(await axiosInstance.post(`/samples/${encodeURIComponent(ref)}/formulas`, p)); if(!isFormula(d)) throw new Error("Invalid"); return d; }
export async function registerFormula(id: number, p: RegisterFormulaPayload){ const d = unwrapData<unknown>(await axiosInstance.post(`/formulas/${id}/register`, p)); if(!isFormula(d)) throw new Error("Invalid"); return d; }
export async function patchFormula(id: number, p: PatchFormulaPayload){ const d = unwrapData<unknown>(await axiosInstance.patch(`/formulas/${id}`, p)); if(!isFormula(d)) throw new Error("Invalid"); return d; }
