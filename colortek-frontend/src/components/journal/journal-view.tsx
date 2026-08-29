"use client";

import PermissionGate from "@/components/auth/permission-gate";
import {
  Alert,
  AlertDescription,
  AlertTitle,
} from "@/components/tailgrids/core/alert";
import {
  Card,
  CardDescription,
  CardTitle,
} from "@/components/tailgrids/core/card";
import { Skeleton } from "@/components/tailgrids/core/skeleton";
import { queryKeys } from "@/lib/queryKeys";
import { getJournalByDate, todayJournalDate } from "@/services/journalService";
import type { Journal } from "@/types/journal";
import { useQuery } from "@tanstack/react-query";
import { formatEnumLabel } from "@/utils/enum-label";

export default function JournalView() {
  return (
    <PermissionGate permission="journal.view">
      <JournalContent />
    </PermissionGate>
  );
}

function JournalContent() {
  const date = todayJournalDate();

  const query = useQuery({
    queryKey: ["journal", date],
    queryFn: () => getJournalByDate(date),
    retry: false,
  });

  return (
    <div className="px-4 pt-6 lg:px-6" dir="auto">
      <div className="mb-6">
        <h1 className="text-2xl font-semibold text-text-primary">Journal</h1>
        <p className="mt-1 text-sm text-text-secondary">
          Today&apos;s journal — {date}
        </p>
      </div>

      {query.isLoading ? <Skeleton className="h-40" /> : null}

      {query.isError ? (
        <Alert status="warning">
          <AlertTitle>No journal for today</AlertTitle>
          <AlertDescription>
            {query.error instanceof Error
              ? query.error.message
              : "Reception prepares the journal when payments are reviewed."}
          </AlertDescription>
        </Alert>
      ) : null}

      {query.isSuccess ? <JournalDetail journal={query.data} /> : null}
    </div>
  );
}

function JournalDetail({ journal }: { journal: Journal }) {
  const isReadOnly =
    journal.status === "submitted" || journal.status === "accounted";

  return (
    <div className="space-y-4">
      {isReadOnly ? (
        <Alert status="info">
          <AlertTitle>Read-only</AlertTitle>
          <AlertDescription>
            This journal was submitted and cannot be edited.
          </AlertDescription>
        </Alert>
      ) : null}

      <Card>
        <CardTitle>Running total</CardTitle>
        <p className="mt-2 text-2xl font-semibold text-text-primary">
          {String(journal.total_amount)}
        </p>
        <CardDescription className="mt-1">
          Status: {formatEnumLabel(journal.status)}
        </CardDescription>
      </Card>

      <Card>
        <CardTitle>Reviewed payments</CardTitle>
        {(journal.payments ?? []).length === 0 ? (
          <CardDescription className="mt-2">
            No payments in this journal yet.
          </CardDescription>
        ) : (
          <ul className="mt-3 divide-y divide-card-border">
            {(journal.payments ?? []).map((payment) => (
              <li key={payment.id} className="py-3 text-sm">
                <p className="font-medium text-text-primary">
                  {payment.project?.reference ?? "—"} ·{" "}
                  {payment.client?.name ?? "—"}
                </p>
                <p className="text-text-secondary">
                  {payment.amount} {payment.currency} · {payment.method} ·{" "}
                  {payment.paid_at}
                </p>
              </li>
            ))}
          </ul>
        )}
      </Card>
    </div>
  );
}
