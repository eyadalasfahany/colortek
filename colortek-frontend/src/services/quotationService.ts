import { axiosInstance } from "@/config/axios";
import type { PaginatedResponse } from "@/types/admin";
import { unwrapData } from "@/types/api";

export interface Quotation {
  id: number;
  number: string;
  client_id: number;
  client?: { id: number; name: string } | null;
  total_value: string;
  currency: string;
  status: string;
  locked_at?: string | null;
  odoo_quotation_id?: string | null;
}

export interface QuotationPayload {
  number: string;
  client_id: number;
  total_value: number | string;
  currency?: string;
  status?: string;
}

export async function getQuotations(
  params: {
    client_id?: number;
    status?: string;
    q?: string;
    per_page?: number;
  } = {},
) {
  return axiosInstance.get<PaginatedResponse<Quotation>>("/quotations", {
    params,
  });
}

export async function createQuotation(
  body: QuotationPayload,
): Promise<Quotation> {
  return unwrapData<Quotation>(
    await axiosInstance.post<unknown>("/quotations", body),
  );
}
