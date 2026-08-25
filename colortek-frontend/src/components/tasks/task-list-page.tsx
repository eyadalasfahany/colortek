"use client";

import { TaskList, TaskListSkeleton } from "@/components/tasks/task-list";
import { Alert, AlertDescription, AlertTitle } from "@/components/tailgrids/core/alert";
import { queryKeys } from "@/lib/queryKeys";
import { getTasks, type TaskScope } from "@/services/taskService";
import { useQuery } from "@tanstack/react-query";

interface TaskListPageProps {
  scope: TaskScope;
  title: string;
  description: string;
  emptyMessage: string;
}

export default function TaskListPage({
  scope,
  title,
  description,
  emptyMessage,
}: TaskListPageProps) {
  const tasksQuery = useQuery({
    queryKey: queryKeys.tasks.list(scope),
    queryFn: () => getTasks(scope),
  });

  return (
    <div className="px-4 pt-6 lg:px-6">
      <div className="mb-6">
        <h1 className="text-2xl font-semibold text-text-primary">{title}</h1>
        <p className="mt-1 text-sm text-text-secondary">{description}</p>
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
        <TaskList tasks={tasksQuery.data.data} emptyMessage={emptyMessage} showClaimAction={scope === "queue"} />
      ) : null}
    </div>
  );
}
