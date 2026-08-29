import { axiosInstance } from "@/config/axios";
import type { PaginatedResponse } from "@/types/admin";
import { unwrapData } from "@/types/api";

export interface Client {
  id: number;
  name: string;
  contact_person?: string | null;
  phone?: string | null;
  email?: string | null;
  address?: string | null;
  notes?: string | null;
  odoo_client_id?: string | null;
  projects_count?: number;
  quotations_count?: number;
}

export interface ClientPayload {
  name: string;
  contact_person?: string | null;
  phone?: string | null;
  email?: string | null;
  address?: string | null;
  notes?: string | null;
}

export async function getClients(
  params: { q?: string; per_page?: number } = {},
) {
  return axiosInstance.get<PaginatedResponse<Client>>("/clients", { params });
}

export async function createClient(body: ClientPayload): Promise<Client> {
  return unwrapData<Client>(
    await axiosInstance.post<unknown>("/clients", body),
  );
}

export async function updateClient(
  id: number,
  body: Partial<ClientPayload>,
): Promise<Client> {
  return unwrapData<Client>(
    await axiosInstance.patch<unknown>(`/clients/${id}`, body),
  );
}
