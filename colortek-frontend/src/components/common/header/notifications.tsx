"use client";

import { BellIcon } from "@/components/common/header/icons";
import { Button } from "@/components/tailgrids/core/button";
import { OverlayWrapper } from "@/components/tailgrids/core/overlay";
import { Popover } from "@/components/tailgrids/core/popover";
import { ScrollArea, ScrollAreaViewport, ScrollBar } from "@/components/tailgrids/core/scroll-area";
import { queryKeys } from "@/lib/queryKeys";
import {
  getNotifications,
  getUnreadNotificationCount,
  markAllNotificationsRead,
  markNotificationRead,
} from "@/services/notificationService";
import { cn } from "@/utils/cn";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import Link from "next/link";
import { useState } from "react";
import { Header, Heading } from "react-aria-components";
import { formatDistanceToNow } from "date-fns";

export function NotificationsButton() {
  const [isOpen, setIsOpen] = useState(false);
  const queryClient = useQueryClient();

  const unreadQuery = useQuery({
    queryKey: queryKeys.notifications.unread(),
    queryFn: getUnreadNotificationCount,
    refetchInterval: 60_000,
  });

  const notificationsQuery = useQuery({
    queryKey: queryKeys.notifications.list(),
    queryFn: () => getNotifications({ per_page: 20 }),
    enabled: isOpen,
  });

  const markReadMutation = useMutation({
    mutationFn: markNotificationRead,
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: queryKeys.notifications.list() });
      void queryClient.invalidateQueries({ queryKey: queryKeys.notifications.unread() });
    },
  });

  const markAllMutation = useMutation({
    mutationFn: markAllNotificationsRead,
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: queryKeys.notifications.list() });
      void queryClient.invalidateQueries({ queryKey: queryKeys.notifications.unread() });
    },
  });

  const unreadCount = unreadQuery.data ?? 0;
  const notifications = notificationsQuery.data?.data ?? [];

  return (
    <OverlayWrapper isOpen={isOpen} onOpenChange={setIsOpen}>
      <Button
        iconOnly
        appearance="outline"
        className="relative size-10 rounded-lg border border-card-border bg-card-background text-icon-primary shadow-xs focus-visible:border-input-primary-focus-border focus-visible:ring-4 focus-visible:ring-input-primary-focus-border/20 [&>svg]:size-auto"
      >
        <BellIcon />
        {unreadCount > 0 && (
          <span className={cn("absolute top-2 right-2.75 z-1 size-2 rounded-full bg-red-400")}>
            <span className="absolute inset-0 -z-1 animate-ping rounded-full bg-red-400 opacity-75" />
          </span>
        )}
      </Button>

      <Popover
        placement="bottom end"
        className="w-84.5 overflow-hidden rounded-2xl border border-border-secondary-alt bg-background-white-secondary p-0 shadow-3xl"
      >
        <Header className="flex items-center justify-between border-b border-border-secondary-alt px-5 pt-5 pb-4">
          <Heading level={4} className="leading-6 font-semibold text-text-primary">
            Notifications
          </Heading>
        </Header>

        <ScrollArea className="h-100 max-h-100">
          <ScrollAreaViewport>
            {notificationsQuery.isLoading ? (
              <p className="p-4 text-sm text-text-tertiary">Loading…</p>
            ) : notifications.length === 0 ? (
              <p className="p-4 text-sm text-text-tertiary">No notifications.</p>
            ) : (
              <ul className="px-3 py-2">
                {notifications.map((notification) => (
                  <li key={notification.id}>
                    <button
                      type="button"
                      className="group flex w-full cursor-pointer gap-3 rounded-lg px-3 py-3 text-start transition-colors hover:bg-background-gray-secondary_alt"
                      onClick={() => {
                        if (!notification.read_at) {
                          markReadMutation.mutate(notification.id);
                        }
                        setIsOpen(false);
                      }}
                    >
                      <div className="min-w-0 flex-1">
                        <p className="text-sm font-medium text-text-primary">{notification.message}</p>
                        {notification.project_reference ? (
                          <p className="mt-0.5 text-xs text-text-secondary">
                            {notification.project_reference}
                          </p>
                        ) : null}
                        <p className="mt-1 text-xs text-text-tertiary">
                          {formatDistanceToNow(new Date(notification.created_at), { addSuffix: true })}
                        </p>
                      </div>
                      {!notification.read_at ? (
                        <div className="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-500" />
                      ) : null}
                    </button>
                  </li>
                ))}
              </ul>
            )}
          </ScrollAreaViewport>
          <ScrollBar />
        </ScrollArea>

        <div className="flex items-center justify-between border-t border-border-secondary-alt px-5 py-4">
          <button
            type="button"
            onClick={() => markAllMutation.mutate()}
            className="text-xs font-medium text-text-secondary underline transition-colors hover:text-text-primary"
          >
            Mark all as read
          </button>
          <Link href="/activity" onClick={() => setIsOpen(false)}>
            <Button variant="primary" size="sm" className="bg-brand-500 py-1.5">
              Activity feed
            </Button>
          </Link>
        </div>
      </Popover>
    </OverlayWrapper>
  );
}
