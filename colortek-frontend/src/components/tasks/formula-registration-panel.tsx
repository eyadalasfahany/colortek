"use client";

import { Button } from "@/components/tailgrids/core/button";
import { Card, CardDescription, CardTitle } from "@/components/tailgrids/core/card";
import { Label } from "@/components/tailgrids/core/label";
import { TextArea } from "@/components/tailgrids/core/text-area";
import { registerFormula } from "@/services/samples/formulaService";
import type { SampleSubjectContext } from "@/types/samples";
import { formatSampleDate } from "@/utils/sample-formatters";
import { useMutation } from "@tanstack/react-query";
import { useState } from "react";

export function FormulaRegistrationPanel({ subject }: { subject: SampleSubjectContext }) {
  const f = subject.formula;
  const [correction, setCorrection] = useState("");
  const [confirmMatches, setConfirmMatches] = useState(false);
  const [message, setMessage] = useState<string | null>(null);

  const registerMutation = useMutation({
    mutationFn: () =>
      registerFormula(f!.id, {
        confirm_matches_sheet: confirmMatches,
        corrections: correction.trim() || undefined,
      }),
    onSuccess: () => setMessage("Formula registered."),
    onError: (error) =>
      setMessage(error instanceof Error ? error.message : "Registration failed."),
  });

  if (!f) {
    return (
      <Card className="mb-4">
        <CardTitle>Formula awaiting author</CardTitle>
        <CardDescription>Tinting must author first.</CardDescription>
      </Card>
    );
  }

  return (
    <Card className="mb-4" dir="auto">
      <CardTitle>Formula to register</CardTitle>
      <CardDescription className="mt-1">
        Compare the authored text and scanned tinting sheet. Corrections are added beside the
        original — never over it.
      </CardDescription>

      <div className="mt-4 grid gap-4 lg:grid-cols-2">
        <div className="rounded-lg border border-card-border p-3">
          <p className="mb-2 text-xs font-semibold uppercase text-text-tertiary">Authored text</p>
          <pre className="whitespace-pre-wrap text-sm">{f.body ?? "Sheet only"}</pre>
          {subject.previous_formula ? (
            <div className="mt-4 border-t border-card-border pt-3">
              <p className="text-xs font-semibold uppercase text-text-tertiary">Previous version</p>
              <pre className="mt-1 whitespace-pre-wrap text-sm text-text-secondary">
                {subject.previous_formula.body ?? "—"}
              </pre>
            </div>
          ) : null}
          {f.corrections?.map((c, i) => (
            <div key={i} className="mt-2 rounded bg-warning-50 p-2 text-sm">
              <p className="line-through">{c.original}</p>
              <p>{c.correction}</p>
            </div>
          ))}
        </div>

        <div className="space-y-3">
          {subject.reference_photo?.url ? (
            <img
              src={subject.reference_photo.url}
              alt="Sample reference"
              className="max-h-48 w-full rounded-lg border border-card-border object-contain"
            />
          ) : null}
          <div className="rounded-lg border border-card-border p-3">
            <p className="mb-2 text-xs font-semibold uppercase text-text-tertiary">Tinting sheet</p>
            {f.formula_sheet?.url ? (
              <img
                src={f.formula_sheet.url}
                alt="Formula sheet scan"
                className="max-h-80 w-full object-contain"
              />
            ) : (
              <CardDescription>No scan uploaded</CardDescription>
            )}
          </div>
        </div>
      </div>

      <p className="mt-3 text-sm text-text-secondary">
        Author: {f.author_employee?.name ?? "—"} · {formatSampleDate(f.authored_at)}
      </p>

      <div className="mt-4 space-y-3 border-t border-card-border pt-4">
        <label className="flex items-center gap-2 text-sm">
          <input
            type="checkbox"
            checked={confirmMatches}
            onChange={(e) => setConfirmMatches(e.target.checked)}
            className="size-4 rounded border-card-border"
          />
          Confirms it matches the sheet
        </label>
        <div>
          <Label htmlFor="formula-correction">Correction (optional)</Label>
          <TextArea
            id="formula-correction"
            value={correction}
            onChange={(e) => setCorrection(e.target.value)}
            placeholder="Type a correction — original text stays visible"
            className="mt-1 min-h-20"
          />
        </div>
        <Button
          variant="primary"
          appearance="fill"
          isDisabled={!confirmMatches || registerMutation.isPending}
          onPress={() => registerMutation.mutate()}
        >
          {registerMutation.isPending ? "Registering…" : "Register formula"}
        </Button>
        {message ? <p className="text-sm text-text-secondary">{message}</p> : null}
      </div>
    </Card>
  );
}
