"use client";

import { NotificationBell } from "@/components/common/notification-bell";
import { NotificationPanel } from "@/components/common/notification-panel";
import { OverlayWrapper } from "@/components/tailgrids/core/overlay";
import { Popover } from "@/components/tailgrids/core/popover";
import { queryKeys } from "@/lib/queryKeys";
import {
  fetchNotifications,
  fetchUnreadCount,
  markAllNotificationsRead,
  markNotificationRead,
} from "@/services/notificationService";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { Header, Heading } from "react-aria-components";

export function NotificationsLive() {
  const [open, setOpen] = useState(false);
  const qc = useQueryClient();

  const unreadQuery = useQuery({
    queryKey: queryKeys.notifications.unread(),
    queryFn: fetchUnreadCount,
    refetchInterval: 30000,
  });

  const listQuery = useQuery({
    queryKey: queryKeys.notifications.list(),
    queryFn: fetchNotifications,
    enabled: open,
  });

  const markRead = useMutation({
    mutationFn: markNotificationRead,
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: queryKeys.notifications.list() });
      void qc.invalidateQueries({ queryKey: queryKeys.notifications.unread() });
    },
  });

  const markAll = useMutation({
    mutationFn: markAllNotificationsRead,
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: queryKeys.notifications.list() });
      void qc.invalidateQueries({ queryKey: queryKeys.notifications.unread() });
    },
  });

  return (
    <OverlayWrapper isOpen={open} onOpenChange={setOpen}>
      <NotificationBell unreadCount={unreadQuery.data ?? 0} onClick={() => setOpen(true)} />
      <Popover
        placement="bottom end"
        className="w-84.5 overflow-hidden rounded-2xl border border-border-secondary-alt bg-background-white-secondary p-0 shadow-3xl"
      >
        <Header className="flex items-center justify-between border-b px-5 pt-5 pb-4">
          <Heading level={4} className="font-semibold text-text-primary">
            Notifications
          </Heading>
        </Header>
        <NotificationPanel
          notifications={listQuery.data ?? []}
          onMarkRead={(id) => markRead.mutate(id)}
          onMarkAllRead={() => markAll.mutate()}
        />
      </Popover>
    </OverlayWrapper>
  );
}
