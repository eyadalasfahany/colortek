"use client";

import { Card, CardDescription, CardTitle } from "@/components/tailgrids/core/card";
import { getToken } from "@/lib/auth-token";
import type { PaymentSubjectContext, PreviousOutputAttachment } from "@/types/api";
import { useEffect, useState } from "react";

interface PaymentSubjectPanelProps {
  subject: PaymentSubjectContext;
}

export function PaymentSubjectPanel({ subject }: PaymentSubjectPanelProps) {
  const methodLabel = subject.method.replace(/_/g, " ");

  return (
    <Card className="mb-4">
      <CardTitle className="mb-4 text-lg">Payment details</CardTitle>
      <CardDescription className="mb-4">
        Submitted by {subject.salesperson?.name ?? "Sales"} — review without re-entering data.
      </CardDescription>

      <dl className="grid gap-3 text-sm sm:grid-cols-2">
        <DetailItem label="Installment" value={`#${subject.installment_number}`} />
        <DetailItem label="Amount" value={`${subject.amount} ${subject.currency}`} />
        <DetailItem label="Method" value={methodLabel} />
        <DetailItem label="Paid on" value={subject.paid_at} />
        {subject.client ? <DetailItem label="Client" value={subject.client.name} /> : null}
        {subject.project ? (
          <DetailItem
            label="Project"
            value={`${subject.project.reference} · ${subject.project.name}`}
          />
        ) : null}
        {subject.quotation ? (
          <DetailItem
            label="Quotation"
            value={`${subject.quotation.number} (${subject.quotation.total_value} ${subject.quotation.currency})`}
          />
        ) : null}
        {subject.notes ? (
          <div className="sm:col-span-2">
            <DetailItem label="Notes" value={subject.notes} />
          </div>
        ) : null}
      </dl>

      {subject.attachments && subject.attachments.length > 0 ? (
        <div className="mt-4 space-y-3">
          <p className="text-xs font-medium uppercase tracking-wide text-text-tertiary">
            Payment proof
          </p>
          {subject.attachments.map((attachment) => (
            <PaymentProofAttachment key={attachment.id} attachment={attachment} />
          ))}
        </div>
      ) : null}
    </Card>
  );
}

function DetailItem({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <dt className="text-xs uppercase text-text-tertiary">{label}</dt>
      <dd className="text-text-primary">{value}</dd>
    </div>
  );
}

function PaymentProofAttachment({ attachment }: { attachment: PreviousOutputAttachment }) {
  const filename = attachment.filename ?? `Attachment #${attachment.id}`;
  const [previewUrl, setPreviewUrl] = useState<string | null>(null);

  useEffect(() => {
    let active = true;
    let objectUrl: string | null = null;

    async function loadPreview() {
      const token = getToken();
      const baseUrl = process.env.NEXT_PUBLIC_API_URL ?? "http://127.0.0.1:8000/api/v1";
      const response = await fetch(`${baseUrl}/attachments/${attachment.id}`, {
        headers: token ? { Authorization: `Bearer ${token}` } : {},
      });

      if (!response.ok || !active) {
        return;
      }

      const blob = await response.blob();
      objectUrl = URL.createObjectURL(blob);

      if (active) {
        setPreviewUrl(objectUrl);
      }
    }

    void loadPreview();

    return () => {
      active = false;
      if (objectUrl) {
        URL.revokeObjectURL(objectUrl);
      }
    };
  }, [attachment.id]);

  return (
    <div className="rounded-lg border border-card-border p-3">
      <p className="text-sm text-text-secondary">{filename}</p>
      {previewUrl ? (
        <img
          src={previewUrl}
          alt={filename}
          className="mt-2 max-h-64 w-full rounded border border-card-border object-contain"
        />
      ) : (
        <p className="mt-2 text-xs text-text-tertiary">Loading preview…</p>
      )}
    </div>
  );
}
