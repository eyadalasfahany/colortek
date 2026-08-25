"use client";

import Link from "next/link";

export function NeedsAttention({
  blockers,
  sitesNotReady,
}: {
  blockers: Array<{ task_id: number; title: string; project_reference?: string }>;
  sitesNotReady: Array<{ project_id: number; reference: string; name: string }>;
}) {
  return (
    <section className="rounded-lg border border-card-border bg-card-background p-4">
      <h2 className="mb-3 text-sm font-semibold text-text-primary">Needs attention</h2>
      {blockers.length > 0 ? (
        <div className="mb-4">
          <p className="mb-2 text-xs uppercase text-text-tertiary">Blockers</p>
          <ul className="space-y-2">
            {blockers.map((b) => (
              <li key={b.task_id}>
                <Link href={`/tasks/${b.task_id}`} className="text-sm text-[var(--color-status-danger,#C6392E)]">
                  {b.project_reference ? `${b.project_reference}: ` : ""}
                  {b.title}
                </Link>
              </li>
            ))}
          </ul>
        </div>
      ) : null}
      {sitesNotReady.length > 0 ? (
        <div>
          <p className="mb-2 text-xs uppercase text-text-tertiary">Sites not ready</p>
          <ul className="space-y-2">
            {sitesNotReady.map((s) => (
              <li key={s.project_id}>
                <Link href={`/projects/${s.reference}`} className="text-sm text-text-primary">
                  {s.reference} — {s.name}
                </Link>
              </li>
            ))}
          </ul>
        </div>
      ) : null}
      {blockers.length === 0 && sitesNotReady.length === 0 ? (
        <p className="text-sm text-text-tertiary">Nothing urgent right now.</p>
      ) : null}
    </section>
  );
}
