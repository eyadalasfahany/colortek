"use client";

import {
  getClaimErrorMessage,
  GroupedTaskList,
  TaskListSkeleton,
} from "@/components/tasks/task-list";
import { Alert, AlertDescription, AlertTitle } from "@/components/tailgrids/core/alert";
import { queryKeys } from "@/lib/queryKeys";
import { getTasks } from "@/services/taskService";
import { useQuery } from "@tanstack/react-query";

export default function MyTasksPage() {
  const tasksQuery = useQuery({
    queryKey: queryKeys.tasks.list("my"),
    queryFn: () => getTasks({ scope: "my", per_page: 100 }),
  });

  return (
    <div className="px-4 pt-6 lg:px-6" dir="auto">
      <div className="mb-6">
        <h1 className="text-2xl font-semibold text-text-primary">My Tasks</h1>
        <p className="mt-1 text-sm text-text-secondary">
          Overdue, today, blocked, and everything else you hold.
        </p>
      </div>

      {tasksQuery.isLoading ? <TaskListSkeleton /> : null}

      {tasksQuery.isError ? (
        <Alert status="error">
          <AlertTitle>Could not load tasks</AlertTitle>
          <AlertDescription>
            {tasksQuery.error instanceof Error
              ? tasksQuery.error.message
              : "Something went wrong."}
          </AlertDescription>
        </Alert>
      ) : null}

      {tasksQuery.isSuccess ? (
        <GroupedTaskList
          tasks={tasksQuery.data.data}
          emptyMessage="You have no tasks. Check the Queue for work waiting for your department."
        />
      ) : null}
    </div>
  );
}
