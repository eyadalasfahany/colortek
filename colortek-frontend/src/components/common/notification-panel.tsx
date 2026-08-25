"use client";

import type { AppNotification } from "@/types/live";
import { cn } from "@/utils/cn";
import Link from "next/link";

function groupNotifications(items: AppNotification[]) {
  const today: AppNotification[] = [];
  const yesterday: AppNotification[] = [];
  const earlier: AppNotification[] = [];
  const now = new Date();
  const startToday = new Date(now.getFullYear(), now.getMonth(), now.getDate());
  const startYesterday = new Date(startToday);
  startYesterday.setDate(startYesterday.getDate() - 1);

  for (const item of items) {
    const d = new Date(item.created_at);
    if (d >= startToday) today.push(item);
    else if (d >= startYesterday) yesterday.push(item);
    else earlier.push(item);
  }

  return [
    { title: "Today", items: today },
    { title: "Yesterday", items: yesterday },
    { title: "Earlier", items: earlier },
  ].filter((g) => g.items.length > 0);
}

export function NotificationPanel({
  notifications,
  onMarkRead,
  onMarkAllRead,
}: {
  notifications: AppNotification[];
  onMarkRead: (id: string) => void;
  onMarkAllRead: () => void;
}) {
  const groups = groupNotifications(notifications);

  return (
    <div className="flex max-h-96 flex-col">
      {groups.map((group) => (
        <section key={group.title}>
          <div className="border-b bg-[var(--color-neutral-50)] px-4 py-2 text-xs uppercase text-text-tertiary">
            {group.title}
          </div>
          <ul>
            {group.items.map((n) => (
              <li key={n.id}>
                <button
                  type="button"
                  onClick={() => onMarkRead(n.id)}
                  className={cn(
                    "w-full px-4 py-3 text-start text-sm hover:bg-[var(--color-neutral-50)]",
                    !n.read_at && "bg-[var(--color-orange)]/5",
                  )}
                >
                  <p className="font-medium text-text-primary">{n.message}</p>
                  {n.link ? (
                    <Link href={n.link} className="mt-1 inline-block text-xs text-[var(--color-orange)]">
                      Open
                    </Link>
                  ) : null}
                </button>
              </li>
            ))}
          </ul>
        </section>
      ))}
      <div className="border-t px-4 py-3">
        <button type="button" onClick={onMarkAllRead} className="text-xs text-text-secondary underline">
          Mark all as read
        </button>
      </div>
    </div>
  );
}
