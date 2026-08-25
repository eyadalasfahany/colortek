"use client";

import Link from "next/link";

export function KpiRow({ kpis }: { kpis: Array<{ key: string; label: string; count: number; filter_href: string }> }) {
  return (
    <div className="grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-7">
      {kpis.map((kpi) => (
        <Link
          key={kpi.key}
          href={kpi.filter_href}
          className="rounded-lg border border-card-border bg-card-background p-3 hover:border-[var(--color-orange)]/40"
        >
          <p className="text-2xl font-semibold text-text-primary">{kpi.count}</p>
          <p className="mt-1 text-xs text-text-secondary">{kpi.label}</p>
        </Link>
      ))}
    </div>
  );
}
