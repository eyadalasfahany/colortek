export interface JournalPayment {
  id: number;
  installment_number: number;
  amount: string;
  currency: string;
  method: string;
  paid_at: string;
  status: string;
  project?: { id: number; reference: string; name: string } | null;
  client?: { id: number; name: string } | null;
  attachments?: Array<{ id: number; filename: string; url?: string }>;
}

export interface Journal {
  id: number;
  journal_date: string;
  status: string;
  total_amount: string | number;
  submitted_at?: string | null;
  accounted_at?: string | null;
  payments_count?: number;
  payments?: JournalPayment[];
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null;
}

export function isJournal(value: unknown): value is Journal {
  return (
    isRecord(value) &&
    typeof value.id === "number" &&
    typeof value.journal_date === "string"
  );
}
