"use client";

import {
  getClaimErrorMessage,
  TaskList,
  TaskListSkeleton,
} from "@/components/tasks/task-list";
import { Alert, AlertDescription, AlertTitle } from "@/components/tailgrids/core/alert";
import { ApiError } from "@/config/axios";
import { queryKeys } from "@/lib/queryKeys";
import { claimTask, getTasks, type TaskScope } from "@/services/taskService";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";

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
  const queryClient = useQueryClient();
  const [claimFeedback, setClaimFeedback] = useState<string | null>(null);
  const [claimingTaskId, setClaimingTaskId] = useState<number | null>(null);

  const tasksQuery = useQuery({
    queryKey: queryKeys.tasks.list(scope),
    queryFn: () => getTasks(scope),
  });

  const claimMutation = useMutation({
    mutationFn: claimTask,
    onMutate: (taskId) => {
      setClaimFeedback(null);
      setClaimingTaskId(taskId);
    },
    onSuccess: async (_task, taskId) => {
      await queryClient.invalidateQueries({ queryKey: queryKeys.tasks.list(scope) });
      await queryClient.invalidateQueries({ queryKey: queryKeys.tasks.detail(taskId) });
      setClaimFeedback(null);
    },
    onError: async (error) => {
      if (error instanceof ApiError && error.status === 409) {
        setClaimFeedback(error.message);
        await queryClient.invalidateQueries({ queryKey: queryKeys.tasks.list(scope) });
        return;
      }

      setClaimFeedback(getClaimErrorMessage(error));
    },
    onSettled: () => {
      setClaimingTaskId(null);
    },
  });

  return (
    <div className="px-4 pt-6 lg:px-6">
      <div className="mb-6">
        <h1 className="text-2xl font-semibold text-text-primary">{title}</h1>
        <p className="mt-1 text-sm text-text-secondary">{description}</p>
      </div>

      {claimFeedback ? (
        <Alert status="warning" className="mb-4">
          <AlertTitle>Could not claim task</AlertTitle>
          <AlertDescription>{claimFeedback}</AlertDescription>
        </Alert>
      ) : null}

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
        <TaskList
          tasks={tasksQuery.data.data}
          emptyMessage={emptyMessage}
          showClaimButton={scope === "queue"}
          claimingTaskId={claimingTaskId}
          onClaim={(taskId) => claimMutation.mutate(taskId)}
        />
      ) : null}
    </div>
  );
}

