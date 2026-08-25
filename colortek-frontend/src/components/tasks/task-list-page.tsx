"use client";

import {
  getClaimErrorMessage,
  TaskList,
  TaskListSkeleton,
} from "@/components/tasks/task-list";
import { Alert, AlertDescription, AlertTitle } from "@/components/tailgrids/core/alert";
import { Input } from "@/components/tailgrids/core/input";
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
  showClaimButton?: boolean;
  showFilters?: boolean;
}

export default function TaskListPage({
  scope,
  title,
  description,
  emptyMessage,
  showClaimButton = false,
  showFilters = false,
}: TaskListPageProps) {
  const queryClient = useQueryClient();
  const [claimFeedback, setClaimFeedback] = useState<string | null>(null);
  const [claimingTaskId, setClaimingTaskId] = useState<number | null>(null);
  const [priority, setPriority] = useState("");
  const [overdueOnly, setOverdueOnly] = useState(false);
  const [projectId, setProjectId] = useState("");

  const tasksQuery = useQuery({
    queryKey: [...queryKeys.tasks.list(scope), priority, overdueOnly, projectId],
    queryFn: () =>
      getTasks({
        scope,
        priority: priority || undefined,
        overdue: overdueOnly || undefined,
        project_id: projectId ? Number(projectId) : undefined,
        per_page: 100,
      }),
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
    <div className="px-4 pt-6 lg:px-6" dir="auto">
      <div className="mb-6">
        <h1 className="text-2xl font-semibold text-text-primary">{title}</h1>
        <p className="mt-1 text-sm text-text-secondary">{description}</p>
      </div>

      {showFilters ? (
        <div className="mb-4 flex flex-col gap-3 sm:flex-row">
          <Input
            value={projectId}
            onChange={(e) => setProjectId(e.target.value)}
            placeholder="Project ID"
            className="w-full px-3 py-2 text-sm sm:max-w-[140px]"
          />
          <select
            value={priority}
            onChange={(e) => setPriority(e.target.value)}
            className="rounded-lg border border-card-border bg-card-bg px-3 py-2 text-sm"
          >
            <option value="">All priorities</option>
            <option value="urgent">Urgent</option>
            <option value="high">High</option>
            <option value="normal">Normal</option>
            <option value="low">Low</option>
          </select>
          <label className="flex items-center gap-2 text-sm text-text-secondary">
            <input
              type="checkbox"
              checked={overdueOnly}
              onChange={(e) => setOverdueOnly(e.target.checked)}
              className="size-4 rounded border-card-border"
            />
            Overdue only
          </label>
        </div>
      ) : null}

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
          showClaimButton={showClaimButton || scope === "queue"}
          claimingTaskId={claimingTaskId}
          onClaim={(taskId) => claimMutation.mutate(taskId)}
        />
      ) : null}
    </div>
  );
}
