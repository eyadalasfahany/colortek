"use client";

import PermissionGate from "@/components/auth/permission-gate";
import TaskListPage from "@/components/tasks/task-list-page";

export default function QueuePage() {
  return (
    <PermissionGate permission="task.view_own_queue">
      <TaskListPage
        scope="queue"
        title="Queue"
        description="Ready tasks in your departments — claim to start work."
        emptyMessage="Nothing waiting for your department."
        showFilters
        showClaimButton
      />
    </PermissionGate>
  );
}
