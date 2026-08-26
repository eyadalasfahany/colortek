"use client";

import PermissionGate from "@/components/auth/permission-gate";
import TaskListPage from "@/components/tasks/task-list-page";
import { useTranslations } from "next-intl";

export default function QueuePage() {
  const t = useTranslations("tasks");

  return (
    <PermissionGate permission="task.view_own_queue">
      <TaskListPage
        scope="queue"
        title={t("queueTitle")}
        description={t("queueDescription")}
        emptyMessage={t("queueEmpty")}
        showFilters
        showClaimButton
      />
    </PermissionGate>
  );
}
