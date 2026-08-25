"use client";

import { ActivityFeedLine } from "@/components/common/activity-feed-line";
import type { ActivityEvent } from "@/types/live";

export function LiveFeed({ events, connected }: { events: ActivityEvent[]; connected: boolean }) {
  return (
    <section className="rounded-lg border border-card-border bg-card-background p-4">
      <div className="mb-3 flex items-center justify-between">
        <h2 className="text-sm font-semibold text-text-primary">Live feed</h2>
        {!connected ? (
          <span className="text-xs text-[var(--color-orange)]">Reconnecting…</span>
        ) : null}
      </div>
      <div className="max-h-96 space-y-1 overflow-y-auto">
        {events.length === 0 ? (
          <p className="text-sm text-text-tertiary">No activity yet.</p>
        ) : (
          events.slice(0, 30).map((event) => <ActivityFeedLine key={event.id} event={event} />)
        )}
      </div>
    </section>
  );
}
