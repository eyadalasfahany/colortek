import { axiosInstance } from "@/config/axios";
import { isJournal, type Journal } from "@/types/journal";
import type { PaginatedResponse } from "@/types/api";
import { unwrapData } from "@/types/api";

function isPaginatedJournals(value: unknown): value is PaginatedResponse<Journal> {
  return (
    typeof value === "object" &&
    value !== null &&
    Array.isArray((value as PaginatedResponse<Journal>).data)
  );
}

export async function getJournals(params?: {
  page?: number;
  per_page?: number;
}): Promise<PaginatedResponse<Journal>> {
  const response = await axiosInstance.get<unknown>("/journals", { params });

  if (!isPaginatedJournals(response)) {
    throw new Error("Invalid journals response");
  }

  return response;
}

export async function getJournalByDate(date: string): Promise<Journal> {
  const data = unwrapData<unknown>(await axiosInstance.get(`/journals/${date}`));

  if (!isJournal(data)) {
    throw new Error("Invalid journal response");
  }

  return data;
}

export function todayJournalDate(): string {
  return new Date().toISOString().slice(0, 10);
}
