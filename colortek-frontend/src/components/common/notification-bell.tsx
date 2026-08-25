"use client";

import { BellIcon } from "@/components/common/header/icons";
import { Button } from "@/components/tailgrids/core/button";
import { cn } from "@/utils/cn";

export function NotificationBell({
  unreadCount,
  onClick,
  className,
}: {
  unreadCount: number;
  onClick?: () => void;
  className?: string;
}) {
  return (
    <Button
      iconOnly
      appearance="outline"
      onPress={onClick}
      className={cn(
        "relative size-10 rounded-lg border border-card-border bg-card-background text-icon-primary shadow-xs",
        className,
      )}
    >
      <BellIcon />
      {unreadCount > 0 ? (
        <span className="absolute top-1.5 right-2 flex h-4 min-w-4 items-center justify-center rounded-full bg-[var(--color-orange)] px-1 text-[10px] font-semibold text-white">
          {unreadCount > 9 ? "9+" : unreadCount}
        </span>
      ) : null}
    </Button>
  );
}
