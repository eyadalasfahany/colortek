"use client";

import { ApiError } from "@/config/axios";
import { Badge } from "@/components/tailgrids/core/badge";
import { Button } from "@/components/tailgrids/core/button";
import { Card, CardDescription, CardHeader, CardTitle } from "@/components/tailgrids/core/card";
import { Skeleton } from "@/components/tailgrids/core/skeleton";
import { queryKeys } from "@/lib/queryKeys";
import { claimTask } from "@/services/taskService";
import type { TaskListItem } from "@/types/api";
import {
  formatStatusLabel,
  formatTaskDueAt,
  priorityLabel,
  statusBadgeColor,
} from "@/utils/task-formatters";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { cn } from "@/utils/cn";

interface TaskListProps {
  tasks: TaskListItem[];
  emptyMessage: string;
  showClaimAction?: boolean;
}

export function TaskList({ tasks, emptyMessage, showClaimAction = false }: TaskListProps) {
  if (tasks.length === 0) {
    return (
      <Card>
        <CardDescription className="text-center text-text-secondary">{emptyMessage}</CardDescription>
      </Card>
    );
  }

  return (
    <div className="flex flex-col gap-3">
      {tasks.map((task) => (
        <TaskListItemCard key={task.id} task={task} showClaimAction={showClaimAction} />
      ))}
    </div>
  );
}

function TaskListItemCard({
  task,
  showClaimAction,
}: {
  task: TaskListItem;
  showClaimAction: boolean;
}) {
  const router = useRouter();
  const queryClient = useQueryClient();
  const priority = priorityLabel(task.priority);

  const claimMutation = useMutation({
    mutationFn: claimTask,
    onSuccess: async (claimed) => {
      await queryClient.invalidateQueries({ queryKey: queryKeys.tasks.all() });
      router.push(`/tasks/${claimed.id}`);
    },
    onError: async (error: unknown) => {
      await queryClient.invalidateQueries({ queryKey: queryKeys.tasks.all() });
      if (error instanceof ApiError && error.status === 409) {
        router.refresh();
      }
    },
  });

  const canClaim = showClaimAction && task.status === "ready";

  return (
    <Card className="transition hover:border-brand-200 hover:shadow-sm">
      <CardHeader className="items-start gap-4">
        <div className="min-w-0 flex-1">
          <p className="text-xs font-medium uppercase tracking-wide text-text-tertiary">
            {task.reference}
          </p>
          <CardTitle className="mt-1 text-base">
            <Link href={`/tasks/${task.id}`} className="hover:text-brand-600">
              {task.title}
            </Link>
          </CardTitle>
          <CardDescription className="mt-2 flex flex-wrap items-center gap-2">
            {task.department ? <span>{task.department.name}</span> : null}
            {task.claimant ? <span>Claimed by {task.claimant.name}</span> : null}
          </CardDescription>
        </div>

        <div className="flex shrink-0 flex-col items-end gap-2">
          <Badge color={statusBadgeColor(task.status)} size="sm">
            {formatStatusLabel(task.status)}
          </Badge>
          {priority ? (
            <Badge color="warning" size="sm">
              {priority}
            </Badge>
          ) : null}
          <span
            className={cn(
              "text-xs",
              task.is_overdue ? "font-medium text-error-500" : "text-text-tertiary",
            )}
          >
            {formatTaskDueAt(task.due_at)}
          </span>
          {canClaim ? (
            <Button
              variant="primary"
              appearance="fill"
              size="sm"
              isDisabled={claimMutation.isPending}
              onPress={() => claimMutation.mutate(task.id)}
            >
              {claimMutation.isPending ? "Claiming…" : "Claim"}
            </Button>
          ) : null}
        </div>
      </CardHeader>
    </Card>
  );
}

export function TaskListSkeleton() {
  return (
    <div className="flex flex-col gap-3">
      {Array.from({ length: 4 }).map((_, index) => (
        <Card key={index}>
          <Skeleton className="h-4 w-24" />
          <Skeleton className="mt-3 h-5 w-2/3" />
          <Skeleton className="mt-2 h-4 w-1/3" />
        </Card>
      ))}
    </div>
  );
}
