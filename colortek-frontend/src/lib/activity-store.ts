import type { ActivityEvent } from "@/types/live";

let events = new Map<number, ActivityEvent>();
let listeners = new Set<() => void>();

export function getActivityEvents(): ActivityEvent[] {
  return [...events.values()].sort((a, b) => b.id - a.id);
}

export function mergeActivityEvents(incoming: ActivityEvent[]): void {
  let changed = false;
  for (const event of incoming) {
    if (!events.has(event.id)) {
      events.set(event.id, event);
      changed = true;
    }
  }
  if (changed) listeners.forEach((l) => l());
}

export function subscribeActivity(listener: () => void): () => void {
  listeners.add(listener);
  return () => listeners.delete(listener);
}

export function resetActivityStore(): void {
  events = new Map();
  listeners.forEach((l) => l());
}
